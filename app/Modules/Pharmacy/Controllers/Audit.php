<?php

namespace Pharmacy\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use Pharmacy\Models\AuditLogModel;
use Pharmacy\Services\OrderFulfilmentService;
use Pharmacy\Services\PharmacyScope;
use DateTimeImmutable;

final class Audit extends BaseController
{
    protected AuditLogModel $logs;

    private PharmacyScope $scope;

    private OrderFulfilmentService $orders;

    public function __construct()
    {
        $this->logs = new AuditLogModel();
        $this->scope = new PharmacyScope();
        $this->orders = new OrderFulfilmentService($this->scope);
    }

    public function index(): string|RedirectResponse
    {
        $clinicId = $this->scope->clinicId();

        if ($clinicId === null) {
            return redirect()->to(base_url('permission-denied'));
        }
        $pageSize = (int) ($this->request->getGet('page_size') ?? 15);
        $pageSize = in_array($pageSize, [10, 15, 25, 50], true) ? $pageSize : 15;
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $search = trim((string) $this->request->getGet('search'));
        $action = trim((string) $this->request->getGet('action'));
        $status = trim((string) $this->request->getGet('status'));
        $from = trim((string) $this->request->getGet('from'));
        $to = trim((string) $this->request->getGet('to'));
        $query = $this->logs->where('clinic_id', $clinicId);

        $matchedOrderId = $this->orderIdForExactSearch($search, $clinicId);

        if ($matchedOrderId > 0) {
            // AuditLogger serializes scalar context values as strings. Match both
            // the current JSON form and older numeric JSON records.
            $query->groupStart()
                ->like('context', '"order_id":"' . $matchedOrderId . '"')
                ->orLike('context', '"order_id":' . $matchedOrderId)
                ->groupEnd();
        } elseif ($search !== '') {
            $query->groupStart()->like('action', $search)->orLike('correlation_id', $search)->orLike('route', $search)->orLike('actor_user_id', $search)->orLike('context', $search)->groupEnd();
        }

        if ($action !== '' && in_array($action, ['security', 'billing', 'prescription', 'medicine', 'order', 'notification', 'patient', 'appointment'], true)) {
            $query->like('action', $action);
        }

        if ($status !== '' && in_array($status, ['created', 'updated', 'deleted', 'failed', 'reviewed', 'read'], true)) {
            $query->like('action', $status);
        }
        $fromIso = $this->toIsoDate($from);
        $toIso = $this->toIsoDate($to);

        if ($fromIso !== null) {
            $query->where('occurred_at >=', $fromIso . ' 00:00:00');
        }

        if ($toIso !== null) {
            $query->where('occurred_at <=', $toIso . ' 23:59:59');
        }
        // Keep the same WHERE clauses for the subsequent page query. A cloned
        // CI4 model shares its builder state, so countAllResults() on the clone
        // would otherwise reset the filtered query and return unrelated events.
        $total = $query->countAllResults(false);
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);
        $events = $query->orderBy('occurred_at', 'ASC')->findAll($pageSize, ($page - 1) * $pageSize);
        $events = $this->presentEvents($events);
        $order = $matchedOrderId > 0 ? $this->orders->detail($matchedOrderId) : null;
        if ($order !== null) {
            $order['payment_history'] = $this->paymentHistory($matchedOrderId, $clinicId);
        }

        return view(PHARMACY_VIEW . 'pages/audit/index', ['events' => $events, 'order' => $order, 'clinicId' => $clinicId, 'filters' => ['search' => $search, 'action' => $action, 'status' => $status, 'from' => $fromIso ? $this->displayDate($fromIso) : '', 'to' => $toIso ? $this->displayDate($toIso) : ''], 'pagination' => ['page' => $page, 'pages' => $pages, 'page_size' => $pageSize, 'total' => $total]]);
    }

    private function orderIdForExactSearch(string $search, int $clinicId): int
    {
        if ($search === '') {
            return 0;
        }

        return (int) (db_connect()->table('medicine_orders')->select('id')->where('clinic_id', $clinicId)->where('order_number', $search)->get(1)->getRow('id') ?? 0);
    }

    private function presentEvents(array $events): array
    {
        $actorIds = array_values(array_unique(array_filter(array_map(static fn (array $event): int => (int) ($event['actor_user_id'] ?? 0), $events))));
        $actors = [];
        if ($actorIds !== []) {
            foreach (db_connect()->table('users')->select('id, username')->whereIn('id', $actorIds)->get()->getResultArray() as $actor) {
                $actors[(int) $actor['id']] = trim((string) ($actor['username'] ?? '')) ?: 'User #' . (int) $actor['id'];
            }
        }

        return array_map(function (array $event) use ($actors): array {
            $context = json_decode((string) ($event['context'] ?? ''), true);
            $context = is_array($context) ? $context : [];
            $event['context_data'] = $context;
            $event['action_label'] = $this->actionLabel((string) ($event['action'] ?? ''));
            $event['description'] = $this->actionDescription((string) ($event['action'] ?? ''), $context);
            $event['actor_label'] = $actors[(int) ($event['actor_user_id'] ?? 0)] ?? ((int) ($event['actor_user_id'] ?? 0) > 0 ? 'User #' . (int) $event['actor_user_id'] : 'System');

            return $event;
        }, $events);
    }

    private function paymentHistory(int $orderId, int $clinicId): array
    {
        if (! db_connect()->tableExists('pharmacy_payment_transactions')) {
            return [];
        }

        return db_connect()->table('pharmacy_payment_transactions transactions')
            ->select('transactions.*, users.username AS recorded_by_username')
            ->join('users', 'users.id = transactions.recorded_by', 'left')
            ->where('transactions.order_id', $orderId)
            ->where('transactions.clinic_id', $clinicId)
            ->orderBy('transactions.paid_at', 'ASC')
            ->orderBy('transactions.id', 'ASC')
            ->get()->getResultArray();
    }

    private function actionLabel(string $action): string
    {
        $labels = ['pharmacy.order_created' => 'Order created', 'pharmacy.order_pricing_created' => 'Order pricing recorded', 'pharmacy.order_cancelled' => 'Order cancelled', 'pharmacy.payment_created' => 'Payment recorded', 'pharmacy.payment_reconciled' => 'Payment status reconciled', 'pharmacy.payment_refunded' => 'Payment refunded', 'pharmacy.delivery_updated' => 'Delivery status updated', 'pharmacy.invoice_share_created' => 'Invoice share link created', 'pharmacy.invoice_downloaded' => 'Invoice downloaded', 'pharmacy.invoice_printed' => 'Invoice printed', 'pharmacy.order_label_preview' => 'Order label previewed', 'pharmacy.order_label_printed' => 'Order label printed', 'pharmacy.prescription_reviewed' => 'Prescription reviewed'];

        return $labels[$action] ?? ucwords(str_replace(['.', '_'], [' / ', ' '], $action ?: 'Audit event'));
    }

    private function actionDescription(string $action, array $context): string
    {
        $status = trim((string) ($context['new_status'] ?? $context['transaction_status'] ?? ''));
        $suffix = $status !== '' ? ' Status: ' . ucwords(str_replace('_', ' ', $status)) . '.' : '';

        return match ($action) {
            'pharmacy.order_created' => 'A medicine order was created from an approved prescription.' . $suffix,
            'pharmacy.order_pricing_created' => 'Medicine pricing, packaging charges, discount, and grand total were recorded.' . $suffix,
            'pharmacy.payment_created' => 'A payment transaction was recorded against the order.' . $suffix,
            'pharmacy.payment_reconciled' => 'The order payment status or provider reference was reconciled.' . $suffix,
            'pharmacy.payment_refunded' => 'A refund transaction was recorded for the order.' . $suffix,
            'pharmacy.order_cancelled' => 'The medicine order was cancelled.' . $suffix,
            'pharmacy.invoice_share_created' => 'A secure share link was created for the order invoice.' . $suffix,
            'pharmacy.invoice_downloaded' => 'The order invoice was downloaded.' . $suffix,
            'pharmacy.invoice_printed' => 'The order invoice was printed.' . $suffix,
            'pharmacy.delivery_updated' => 'The delivery status or tracking information was updated.' . $suffix,
            default => 'This event records a change or action related to the order.' . $suffix,
        };
    }

    private function toIsoDate(string $value): ?string
    {
        $date = DateTimeImmutable::createFromFormat('!d-m-Y', $value);

        return $date !== false && $date->format('d-m-Y') === $value ? $date->format('Y-m-d') : null;
    }

    private function displayDate(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false ? $date->format('d-m-Y') : '';
    }
}
