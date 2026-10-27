<?php

namespace Pharmacy\Services;

use DateTimeImmutable;

final class BillingService
{
    private const PAYMENT_STATUSES = ['pending', 'unpaid', 'authorized', 'partially_paid', 'paid', 'failed', 'refunded', 'cancelled'];

    private const PROVIDERS = ['manual', 'cashfree', 'razorpay'];

    private PaymentStatusCatalog $paymentStatuses;
    private PaymentProviderCatalog $paymentProviders;

    public function __construct(private readonly PharmacyScope $scope, private readonly PaymentProviderBoundary $providers = new PaymentProviderBoundary())
    {
        $this->paymentStatuses = new PaymentStatusCatalog();
        $this->paymentProviders = new PaymentProviderCatalog();
    }

    /**
     * Billing reads authoritative invoice totals while keeping payment-provider
     * credentials and reconciliation transitions on the server boundary.
     */
    public function list(array $input): array
    {
        $clinicId = $this->scope->clinicId();
        $pageSize = (int) ($input['page_size'] ?? 15);
        $pageSize = in_array($pageSize, [10, 15, 25, 50], true) ? $pageSize : 15;
        $page = max(1, (int) ($input['page'] ?? 1));
        $requestedView = (string) ($input['view'] ?? 'list');
        $view = in_array($requestedView, ['list', 'grid'], true) ? $requestedView : 'list';
        $filters = ['search' => trim((string) ($input['search'] ?? '')), 'payment_status' => trim((string) ($input['payment_status'] ?? '')), 'order_status' => trim((string) ($input['order_status'] ?? '')), 'provider' => trim((string) ($input['provider'] ?? '')), 'from' => $this->toIso((string) ($input['from'] ?? '')), 'to' => $this->toIso((string) ($input['to'] ?? '')), 'date_error' => false, 'page_size' => $pageSize];
        $filters['date_error'] = $filters['from'] !== null && $filters['to'] !== null && $filters['to'] < $filters['from'];

        if ($clinicId === null) {
            return $this->emptyPayload($view, $filters, $pageSize);
        }
        $query = db_connect()->table('medicine_orders AS orders')->select('orders.id, orders.order_number, orders.status AS order_status, orders.payment_status, orders.delivery_status, orders.payment_provider, payment_providers.provider_name AS payment_provider_name, orders.payment_reference, orders.last_reconciled_at, orders.created_at, orders.updated_at, patients.registration_number, patients.first_name, patients.middle_name, patients.last_name, patients.mobile_number, patients.country_code, invoices.id AS invoice_id, invoices.invoice_number, invoices.currency, invoices.subtotal, invoices.tax, invoices.discount, invoices.total AS invoice_total, invoices.courier_charges, invoices.discount_type, invoices.discount_value, invoices.status AS invoice_status, invoices.payment_status AS invoice_payment_status, invoices.created_at AS invoice_created_at, invoices.updated_at AS invoice_updated_at, (SELECT COUNT(*) FROM medicine_order_items count_items WHERE count_items.order_id = orders.id) AS medicine_count, (SELECT COALESCE(SUM(items.line_total), 0) FROM medicine_order_items items WHERE items.order_id = orders.id) AS order_total')->join('patients', 'patients.id = orders.patient_id')->join('payment_providers', 'payment_providers.provider_code = orders.payment_provider', 'left')->join('invoices', 'invoices.order_id = orders.id', 'left')->where('orders.clinic_id', $clinicId)->where('patients.clinic_id', $clinicId)->where('(invoices.id IS NULL OR invoices.clinic_id = ' . (int) $clinicId . ')', null, false);

        if ($filters['date_error']) {
            $query->where('1 = 0', null, false);
        }

        if (in_array($filters['payment_status'], self::PAYMENT_STATUSES, true)) {
            $query->where('orders.payment_status', $filters['payment_status']);
        }

        if (in_array($filters['order_status'], ['pending', 'fulfilled', 'cancelled'], true)) {
            $query->where('orders.status', $filters['order_status']);
        }

        if (in_array($filters['provider'], self::PROVIDERS, true) || $this->paymentProviders->isActive($filters['provider'])) {
            $query->where('orders.payment_provider', $filters['provider']);
        }

        if (!$filters['date_error'] && $filters['from'] !== null) {
            $query->where('orders.created_at >=', $filters['from'] . ' 00:00:00');
        }

        if (!$filters['date_error'] && $filters['to'] !== null) {
            $query->where('orders.created_at <=', $filters['to'] . ' 23:59:59');
        }

        if ($filters['search'] !== '') {
            $query->groupStart()->like('orders.order_number', $filters['search'])->orLike('invoices.invoice_number', $filters['search'])->orLike('patients.first_name', $filters['search'])->orLike('patients.middle_name', $filters['search'])->orLike('patients.last_name', $filters['search'])->orLike('patients.registration_number', $filters['search'])->orLike('patients.mobile_number', $filters['search'])->orLike('patients.mobile_normalized', $filters['search'])->orLike('orders.payment_reference', $filters['search'])->orLike('orders.payment_provider', $filters['search'])->groupEnd();
        }
        $total = (clone $query)->countAllResults();
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);
        $records = $query->orderBy('orders.id', 'DESC')->get($pageSize, ($page - 1) * $pageSize)->getResultArray();

        return ['rows' => array_map(fn (array $row): array => $this->normalize($row), $records), 'view' => $view, 'filters' => $this->displayFilters($filters), 'pagination' => ['page' => $page, 'pages' => $pages, 'page_size' => $pageSize, 'total' => $total], 'readiness' => ['cashfree' => $this->providers->readiness('cashfree'), 'razorpay' => $this->providers->readiness('razorpay')]];
    }

    public function detail(int $orderId): ?array
    {
        $clinicId = $this->scope->clinicId();

        if ($clinicId === null || $orderId <= 0) {
            return null;
        }
        $row = db_connect()->table('medicine_orders AS orders')->select('orders.*, payment_providers.provider_name AS payment_provider_name, clinics.clinic_name, patients.registration_number, patients.first_name, patients.middle_name, patients.last_name, patients.mobile_number, patients.country_code, invoices.id AS invoice_id, invoices.invoice_number, invoices.currency, invoices.subtotal, invoices.tax, invoices.discount, invoices.total AS invoice_total, invoices.courier_charges, invoices.discount_type, invoices.discount_value, invoices.status AS invoice_status, invoices.payment_status AS invoice_payment_status, invoices.created_at AS invoice_created_at, invoices.updated_at AS invoice_updated_at')->join('clinics', 'clinics.id = orders.clinic_id')->join('payment_providers', 'payment_providers.provider_code = orders.payment_provider', 'left')->join('patients', 'patients.id = orders.patient_id')->join('invoices', 'invoices.order_id = orders.id AND invoices.clinic_id = orders.clinic_id', 'left')->where('orders.id', $orderId)->where('orders.clinic_id', $clinicId)->where('patients.clinic_id', $clinicId)->get()->getRowArray();

        if ($row === null) {
            return null;
        }
        $row = $this->normalize($row);
        $row['items'] = db_connect()->table('invoice_items')->where('invoice_id', (int) ($row['invoice_id'] ?? 0))->orderBy('id', 'ASC')->get()->getResultArray();
        $row['payment_history'] = $this->paymentHistory($orderId);

        return $row;
    }

    /** @return array{token:string,expires_at:string}|null */
    public function createInvoiceShare(int $orderId): ?array
    {
        $clinicId = $this->scope->clinicId();
        if ($clinicId === null || $orderId <= 0 || $this->detail($orderId) === null || !db_connect()->tableExists('pharmacy_invoice_shares')) {
            return null;
        }

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $expiresAt = (new DateTimeImmutable('+7 days'))->format('Y-m-d H:i:s');
        $user = session('user');
        db_connect()->table('pharmacy_invoice_shares')->insert([
            'clinic_id' => $clinicId,
            'order_id' => $orderId,
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt,
            'created_by' => is_array($user) ? ((int) ($user['id'] ?? 0) ?: null) : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->scope->audit('pharmacy.invoice_share_created', ['order_id' => $orderId, 'expires_at' => $expiresAt]);

        return ['token' => $token, 'expires_at' => $expiresAt];
    }

    public function sharedInvoice(string $token): ?array
    {
        $token = trim($token);
        if (!preg_match('/^[A-Za-z0-9_-]{40,64}$/', $token) || !db_connect()->tableExists('pharmacy_invoice_shares')) {
            return null;
        }
        $share = db_connect()->table('pharmacy_invoice_shares')->where('token_hash', hash('sha256', $token))->where('revoked_at IS NULL', null, false)->where('expires_at >=', date('Y-m-d H:i:s'))->get(1)->getRowArray();
        if ($share === null) {
            return null;
        }
        db_connect()->table('pharmacy_invoice_shares')->where('id', (int) $share['id'])->update(['accessed_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        $clinicId = (int) $share['clinic_id'];
        $row = db_connect()->table('medicine_orders AS orders')->select('orders.*, payment_providers.provider_name AS payment_provider_name, clinics.clinic_name, patients.registration_number, patients.first_name, patients.middle_name, patients.last_name, patients.mobile_number, patients.country_code, invoices.id AS invoice_id, invoices.invoice_number, invoices.currency, invoices.subtotal, invoices.tax, invoices.discount, invoices.total AS invoice_total, invoices.courier_charges, invoices.discount_type, invoices.discount_value, invoices.status AS invoice_status, invoices.payment_status AS invoice_payment_status, invoices.created_at AS invoice_created_at, invoices.updated_at AS invoice_updated_at')->join('clinics', 'clinics.id = orders.clinic_id')->join('payment_providers', 'payment_providers.provider_code = orders.payment_provider', 'left')->join('patients', 'patients.id = orders.patient_id')->join('invoices', 'invoices.order_id = orders.id AND invoices.clinic_id = orders.clinic_id', 'left')->where('orders.id', (int) $share['order_id'])->where('orders.clinic_id', $clinicId)->where('patients.clinic_id', $clinicId)->get()->getRowArray();
        if ($row === null) {
            return null;
        }
        $row = $this->normalize($row, $clinicId);
        $row['items'] = db_connect()->table('invoice_items')->where('invoice_id', (int) ($row['invoice_id'] ?? 0))->orderBy('id', 'ASC')->get()->getResultArray();
        $row['payment_history'] = $this->paymentHistory((int) $share['order_id'], $clinicId);
        $row['share_expires_at'] = $share['expires_at'];
        $row['share_token'] = $token;

        return $row;
    }

    /**
     * Record one immutable payment attempt or successful payment transaction.
     * Existing reconciliation remains available for legacy status-only records.
     */
    public function recordPayment(int $orderId, string $amountInput, string $paymentMethod, string $provider, string $reference, string $notes, string $idempotencyKey, string $transactionStatus = 'succeeded', string $paymentDate = '', string $paymentTime = ''): array
    {
        $clinicId = $this->scope->clinicId();
        $paymentMethod = strtolower(trim($paymentMethod));
        $provider = strtolower(trim($provider));
        $reference = trim($reference) !== '' ? trim($reference) : null;
        $notes = trim($notes) !== '' ? trim($notes) : null;
        $transactionStatus = strtolower(trim($transactionStatus ?: 'succeeded'));
        $idempotencyKey = trim($idempotencyKey);

        [$paidAtValue, $timestampErrors] = $this->paymentTimestamp($paymentDate, $paymentTime);
        if ($timestampErrors !== []) {
            return $this->result(422, 'Payment date or time is invalid.', $timestampErrors);
        }

        if ($clinicId === null) {
            return $this->result(403, 'Pharmacy clinic scope is not assigned.');
        }
        if (!preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/', trim($amountInput))) {
            return $this->result(422, 'Payment amount is invalid.', ['amount' => 'Enter a valid non-negative amount.']);
        }
        $amountCents = $this->moneyCents((float) $amountInput);
        if ($transactionStatus === 'succeeded' && $amountCents <= 0) {
            return $this->result(422, 'Payment amount is required.', ['amount' => 'Enter a payment amount greater than zero.']);
        }
        if (!in_array($transactionStatus, ['succeeded', 'failed', 'cancelled'], true)) {
            return $this->result(422, 'Payment result is invalid.', ['transaction_status' => 'Choose a valid payment result.']);
        }
        if ($paymentMethod === '' || !in_array($paymentMethod, ['cash', 'card', 'upi', 'bank_transfer', 'online', 'manual'], true)) {
            return $this->result(422, 'Payment method is invalid.', ['payment_method' => 'Choose a supported payment method.']);
        }
        if ($provider !== '' && $this->paymentProviders->findActive($provider) === null) {
            return $this->result(422, 'Payment provider is invalid.', ['provider' => 'Choose a supported provider.']);
        }
        if ($idempotencyKey === '') {
            $idempotencyKey = hash('sha256', implode('|', [$orderId, $amountCents, $paymentMethod, $provider, $reference ?? '', $paidAtValue, $notes ?? '']));
        }
        if (strlen($idempotencyKey) > 120) {
            return $this->result(422, 'Payment request key is invalid.', ['idempotency_key' => 'The payment request key is too long.']);
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $existing = $db->table('pharmacy_payment_transactions')->where('clinic_id', $clinicId)->where('idempotency_key', $idempotencyKey)->get(1)->getRowArray();
            if ($existing !== null) {
                if ((int) ($existing['order_id'] ?? 0) !== $orderId) {
                    return $this->rollbackResult($db, 409, 'This payment request key is already assigned to another order.', ['idempotency_key' => 'Use a new payment request key for this order.']);
                }
                $sameRequest = $this->moneyCents((float) ($existing['amount'] ?? 0)) === $amountCents
                    && strtolower((string) ($existing['payment_method'] ?? '')) === $paymentMethod
                    && strtolower((string) ($existing['provider'] ?? '')) === ($provider !== '' ? $provider : '')
                    && (string) ($existing['reference_number'] ?? '') === (string) ($reference ?? '');
                if (!$sameRequest) {
                    return $this->rollbackResult($db, 409, 'This payment request key was already used with different payment details.', ['idempotency_key' => 'Use a new payment request key for a changed payment.']);
                }
                $duplicateOrder = $db->table('medicine_orders AS orders')
                    ->select('invoices.total AS invoice_total')
                    ->join('invoices', 'invoices.order_id = orders.id AND invoices.clinic_id = orders.clinic_id', 'left')
                    ->where('orders.id', $orderId)->where('orders.clinic_id', $clinicId)->get(1)->getRowArray();
                $db->transCommit();
                $summary = $this->paymentSummary($orderId, (float) ($duplicateOrder['invoice_total'] ?? 0));

                return ['http' => 200, 'body' => ['status' => true, 'msg' => 'This payment request was already recorded.', 'data' => ['duplicate' => true, 'transaction' => $existing, 'payment_summary' => $summary]]];
            }

            $order = $db->query('SELECT orders.*, invoices.id AS invoice_id, invoices.total AS invoice_total FROM medicine_orders orders LEFT JOIN invoices ON invoices.order_id = orders.id AND invoices.clinic_id = orders.clinic_id JOIN patients ON patients.id = orders.patient_id AND patients.clinic_id = ? WHERE orders.id = ? AND orders.clinic_id = ? LIMIT 1 FOR UPDATE', [$clinicId, $orderId, $clinicId])->getRowArray();
            if ($order === null) {
                throw new \RuntimeException('Order not found in the assigned clinic.');
            }
            if ((string) ($order['status'] ?? '') === 'cancelled' && $transactionStatus === 'succeeded') {
                return $this->rollbackResult($db, 422, 'Cancelled orders cannot receive a new payment.', ['amount' => 'This order is cancelled.']);
            }

            $totalCents = $this->moneyCents((float) ($order['invoice_total'] ?? 0));
            $totals = $this->transactionTotals($orderId, $clinicId);
            $remainingCents = max(0, $totalCents - $totals['paid_cents'] + $totals['refunded_cents']);
            if ($transactionStatus === 'succeeded' && $amountCents > $remainingCents) {
                return $this->rollbackResult($db, 422, 'Payment exceeds the remaining balance.', ['amount' => 'Enter no more than ' . number_format($remainingCents / 100, 2, '.', '') . '.']);
            }

            $db->table('pharmacy_payment_transactions')->insert([
                'order_id' => $orderId,
                'invoice_id' => !empty($order['invoice_id']) ? (int) $order['invoice_id'] : null,
                'clinic_id' => $clinicId,
                'transaction_type' => 'payment',
                'status' => $transactionStatus,
                'amount' => number_format($amountCents / 100, 2, '.', ''),
                'payment_method' => $paymentMethod,
                'provider' => $provider !== '' ? $provider : null,
                'reference_number' => $reference,
                'notes' => $notes,
                'paid_at' => $paidAtValue,
                'recorded_by' => (int) (($this->scope->user() ?? [])['id'] ?? 0) ?: null,
                'idempotency_key' => $idempotencyKey,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            if (!$db->transStatus()) {
                throw new \RuntimeException('Payment transaction could not be recorded.');
            }

            $newStatus = $this->refreshPaymentState($db, $orderId, $clinicId, $order);
            $db->transCommit();
            $this->scope->audit('pharmacy.payment_created', ['order_id' => $orderId, 'invoice_id' => $order['invoice_id'] ?? null, 'amount' => $amountCents / 100, 'transaction_status' => $transactionStatus, 'payment_method' => $paymentMethod, 'provider' => $provider, 'reference' => $reference, 'idempotency_key' => $idempotencyKey]);

            return ['http' => 200, 'body' => ['status' => true, 'msg' => $transactionStatus === 'succeeded' ? 'Payment recorded successfully.' : 'Payment attempt recorded.', 'data' => ['payment_status' => $newStatus, 'payment_summary' => $this->paymentSummary($orderId, $totalCents / 100)]]];
        } catch (\Throwable $exception) {
            $db->transRollback();
            log_message('error', 'Pharmacy payment transaction failed: ' . $exception->getMessage());

            return $this->result(500, 'Payment could not be recorded safely.');
        }
    }

    public function reconcile(int $orderId, string $status, string $provider, string $reference, string $reason, bool $confirmed): array
    {
        $scope = $this->scope->clinicId();
        $status = strtolower(trim($status));
        $provider = strtolower(trim($provider));
        $reference = trim($reference) !== '' ? trim($reference) : null;
        $reason = trim($reason) !== '' ? trim($reason) : null;

        if ($scope === null) {
            return $this->result(403, 'Pharmacy clinic scope is not assigned.');
        }

        if (!in_array($status, self::PAYMENT_STATUSES, true)) {
            return $this->result(422, 'Payment status is invalid.', ['payment_status' => 'Choose a valid payment status.']);
        }

        if (!$this->paymentStatuses->isActive($status)) {
            return $this->result(422, 'Payment status is inactive or unavailable.', ['payment_status' => 'Choose an active payment status.']);
        }

        if ($provider === '') {
            return $this->result(422, 'Provider is required.', ['provider' => 'Provider is required.']);
        }

        if ($this->paymentProviders->findActive($provider) === null) {
            return $this->result(422, 'Payment provider is invalid.', ['provider' => 'Choose a supported provider.']);
        }

        if (!$confirmed) {
            return $this->result(422, 'Manual reconciliation confirmation is required.', ['confirmation' => 'Confirm that this payment was verified manually.']);
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $order = $db->query('SELECT orders.*, invoices.total AS invoice_total, invoices.id AS invoice_id FROM medicine_orders orders JOIN patients ON patients.id = orders.patient_id AND patients.clinic_id = ? LEFT JOIN invoices ON invoices.order_id = orders.id AND invoices.clinic_id = ? WHERE orders.id = ? AND orders.clinic_id = ? LIMIT 1 FOR UPDATE', [$scope, $scope, $orderId, $scope])->getRowArray();

            if ($order === null) {
                $db->transRollback();

                return $this->result(404, 'Order not found in the assigned clinic.');
            }

            if ((string) $order['payment_status'] === $status && strtolower(trim((string) ($order['payment_provider'] ?? ''))) === $provider && (string) ($order['payment_reference'] ?? '') === (string) ($reference ?? '')) {
                $db->transCommit();

                return ['http' => 200, 'body' => ['status' => true, 'msg' => 'Payment was already reconciled.', 'data' => ['duplicate' => true]]];
            }

            $updated = $db->table('medicine_orders')->where('id', $orderId)->where('clinic_id', $scope)->where('payment_status', $order['payment_status'])->update(['payment_status' => $status, 'payment_provider' => $provider, 'payment_reference' => $reference, 'last_reconciled_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);

            if (!$updated || !$db->transStatus()) {
                throw new \RuntimeException('Payment update failed.');
            }

            if (!empty($order['invoice_id'])) {
                $invoiceUpdated = $db->table('invoices')->where('id', (int) $order['invoice_id'])->where('clinic_id', $scope)->update(['payment_status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);

                if (!$invoiceUpdated || !$db->transStatus()) {
                    throw new \RuntimeException('Invoice payment status update failed.');
                }
            }
            $db->transCommit();
            $this->scope->audit('pharmacy.payment_reconciled', ['order_id' => $orderId, 'invoice_id' => $order['invoice_id'] ?? null, 'previous_status' => $order['payment_status'], 'new_status' => $status, 'amount' => (float) ($order['invoice_total'] ?? 0), 'provider' => $provider, 'reference' => $reference, 'reason' => $reason, 'reconciliation_source' => 'manual']);
            (new PharmacyNotificationService($this->scope))->create('payment_reconciliation', 'Payment status updated', 'Payment for order #' . $orderId . ' is now ' . $status . '.', 'billing', $orderId, 'payment-reconciled:' . $orderId . ':' . $status . ':' . ($reference ?? ''));
            (new \Patient\Services\PatientNotificationService())->forOrder($orderId, 'payment_status_changed', 'Payment status updated', 'Payment for your medicine order is now ' . $status . '.', 'patient-payment:' . $orderId . ':' . $status . ':' . ($reference ?? ''));

            return ['http' => 200, 'body' => ['status' => true, 'msg' => 'Saved as manual payment reconciliation; no external provider call was made.', 'data' => ['reconciliation_source' => 'manual', 'external_call' => false]]];
        } catch (\Throwable $exception) {
            $db->transRollback();
            log_message('error', 'Pharmacy billing reconciliation failed: ' . $exception->getMessage());

            return $this->result(500, 'Payment status could not be updated safely.');
        }
    }

    public function refund(int $orderId, float $amount, string $provider, string $reference, string $reason, bool $confirmed): array
    {
        $detail = $this->detail($orderId);

        if ($detail === null) {
            return $this->result(404, 'Order not found in the assigned clinic.');
        }

        if ((string) $detail['payment_status'] === 'refunded') {
            return $this->result(422, 'This payment has already been refunded.', ['amount' => 'Duplicate refunds are not allowed.']);
        }
        $paidAmount = (float) ($detail['paid_amount'] ?? $detail['invoice_total'] ?? 0);
        $refundableAmount = (float) ($detail['refundable_amount'] ?? $paidAmount);
        $provider = strtolower(trim($provider));
        $reference = trim($reference) !== '' ? trim($reference) : null;
        $reason = trim($reason) !== '' ? trim($reason) : null;

        if ((string) $detail['payment_status'] !== 'paid') {
            return $this->result(422, 'Only paid orders can be refunded.', ['amount' => 'The order payment is not paid.']);
        }

        if ($amount <= 0 || $amount > $refundableAmount) {
            return $this->result(422, 'Refund amount is invalid.', ['amount' => 'Refund cannot exceed the refundable paid amount of ' . number_format($refundableAmount, 2, '.', '') . '.']);
        }

        if (!$confirmed) {
            return $this->result(422, 'Refund confirmation is required.', ['confirmation' => 'Confirm this refund request.']);
        }

        $providerRecord = $this->paymentProviders->findActive($provider);
        if ($providerRecord === null || ($providerRecord['provider_type'] ?? 'manual') === 'manual') {
            return $this->result(422, 'Payment provider is invalid.', ['provider' => 'Choose a supported refund provider.']);
        }

        if (!$this->providers->isConfigured($provider)) {
            $this->scope->audit('pharmacy.provider_not_configured', ['workflow' => 'refund', 'provider' => $provider, 'order_id' => $orderId, 'amount' => $amount]);
            (new PharmacyNotificationService($this->scope))->create('refund_result', 'Refund could not be processed', 'Refund provider is not configured for order #' . $orderId . '.', 'billing', $orderId, 'refund-result:' . $orderId . ':' . $reference);

            return $this->result(503, 'Provider is not configured; no refund was processed.');
        }
        $this->scope->audit('pharmacy.provider_not_configured', ['workflow' => 'refund_adapter', 'provider' => $provider, 'order_id' => $orderId, 'amount' => $amount]);

        return $this->result(503, 'Provider refund adapter is not enabled; no refund was processed.');
    }

    private function normalize(array $row, ?int $clinicId = null): array
    {
        $name = trim(implode(' ', array_filter([$row['first_name'] ?? '', $row['middle_name'] ?? '', $row['last_name'] ?? ''])));
        $amount = (float) ($row['invoice_total'] ?? $row['order_total'] ?? 0);
        $summary = $this->paymentSummary((int) ($row['id'] ?? 0), $amount, $clinicId);
        $paymentStatus = $summary['payment_status'];
        $paidAmount = $summary['paid_amount'];
        $refundedAmount = $summary['refunded_amount'];

        return ['id' => (int) $row['id'], 'order_number' => (string) ($row['order_number'] ?? 'Not recorded'), 'patient_name' => $name ?: 'Patient not recorded', 'patient_number' => (string) ($row['registration_number'] ?? 'Not recorded'), 'patient_mobile' => trim(implode(' ', array_filter([(string) ($row['country_code'] ?? ''), (string) ($row['mobile_number'] ?? '')])) ?: 'Not recorded'), 'medicine_count' => (int) ($row['medicine_count'] ?? 0), 'invoice_id' => $row['invoice_id'] === null ? null : (int) $row['invoice_id'], 'invoice_number' => (string) ($row['invoice_number'] ?? 'Unavailable'), 'amount' => $amount, 'subtotal' => (float) ($row['subtotal'] ?? $amount), 'tax' => (float) ($row['tax'] ?? 0), 'courier_charges' => (float) ($row['courier_charges'] ?? 0), 'discount' => (float) ($row['discount'] ?? 0), 'discount_type' => (string) ($row['discount_type'] ?? 'fixed'), 'discount_value' => (float) ($row['discount_value'] ?? 0), 'paid_amount' => $paidAmount, 'remaining_balance' => $summary['remaining_balance'], 'refunded_amount' => $refundedAmount, 'refundable_amount' => max(0.0, $paidAmount - $refundedAmount), 'currency' => (string) ($row['currency'] ?? 'INR'), 'invoice_status' => (string) ($row['invoice_status'] ?? 'unavailable'), 'payment_status' => $paymentStatus, 'payment_status_label' => $summary['payment_status_label'], 'payment_reference' => (string) ($row['payment_reference'] ?? 'Not recorded'), 'provider' => (string) ($row['payment_provider'] ?? 'Not recorded'), 'provider_name' => (string) ($row['payment_provider_name'] ?? $row['payment_provider'] ?? 'Not recorded'), 'order_status' => (string) ($row['order_status'] ?? ''), 'created_at' => $this->displayDate($row['created_at'] ?? ''), 'updated_at' => $this->displayDate($row['updated_at'] ?? ''), 'invoice_created_at' => $this->displayDate($row['invoice_created_at'] ?? ''), 'invoice_updated_at' => $this->displayDate($row['invoice_updated_at'] ?? '')];
    }

    /** @return array<string, mixed> */
    private function paymentSummary(int $orderId, float $total, ?int $clinicId = null): array
    {
        $totalCents = $this->moneyCents($total);
        $clinicId ??= $this->scope->clinicId() ?? 0;
        $totals = $this->transactionTotals($orderId, $clinicId);
        $paidCents = $totals['paid_cents'];
        $refundedCents = $totals['refunded_cents'];
        $netPaidCents = max(0, $paidCents - $refundedCents);
        $status = $totals['has_transactions'] ? $this->derivedPaymentStatus($totalCents, $netPaidCents, $refundedCents, $totals['has_failed']) : 'pending';

        if (!$totals['has_transactions']) {
            $legacy = db_connect()->table('medicine_orders')->select('payment_status')->where('id', $orderId)->where('clinic_id', $clinicId)->get(1)->getRowArray();
            $legacyStatus = strtolower((string) ($legacy['payment_status'] ?? 'pending'));
            if (in_array($legacyStatus, ['paid', 'refunded', 'failed', 'authorized', 'partially_paid', 'cancelled'], true)) {
                $status = $legacyStatus;
                if ($legacyStatus === 'paid') $paidCents = $totalCents;
                if ($legacyStatus === 'refunded') { $paidCents = $totalCents; $refundedCents = $totalCents; $netPaidCents = 0; }
            }
        }

        $remainingCents = ($status === 'refunded' && $refundedCents >= $totalCents) ? 0 : max(0, $totalCents - $netPaidCents);
        return ['total_amount' => $totalCents / 100, 'paid_amount' => $paidCents / 100, 'refunded_amount' => $refundedCents / 100, 'remaining_balance' => $remainingCents / 100, 'payment_status' => $status, 'payment_status_label' => $this->paymentStatusLabel($status), 'has_transactions' => $totals['has_transactions']];
    }

    /** @return array{paid_cents:int, refunded_cents:int, has_transactions:bool, has_failed:bool} */
    private function transactionTotals(int $orderId, int $clinicId): array
    {
        $result = ['paid_cents' => 0, 'refunded_cents' => 0, 'has_transactions' => false, 'has_failed' => false];
        $db = db_connect();
        if ($orderId <= 0 || $clinicId <= 0 || !$db->tableExists('pharmacy_payment_transactions')) return $result;
        foreach ($db->table('pharmacy_payment_transactions')->select('transaction_type, status, amount')->where('order_id', $orderId)->where('clinic_id', $clinicId)->get()->getResultArray() as $transaction) {
            $result['has_transactions'] = true;
            $status = strtolower((string) ($transaction['status'] ?? ''));
            $amountCents = $this->moneyCents((float) ($transaction['amount'] ?? 0));
            if ($status === 'failed' || $status === 'cancelled') $result['has_failed'] = true;
            if ($status !== 'succeeded') continue;
            if (($transaction['transaction_type'] ?? 'payment') === 'refund') $result['refunded_cents'] += $amountCents;
            else $result['paid_cents'] += $amountCents;
        }
        return $result;
    }

    /** @return array<int, array<string, mixed>> */
    private function paymentHistory(int $orderId, ?int $clinicId = null): array
    {
        $db = db_connect();
        if ($orderId <= 0 || !$db->tableExists('pharmacy_payment_transactions')) return [];
        return $db->table('pharmacy_payment_transactions AS transactions')->select('transactions.*, users.username AS recorded_by_username')->join('users', 'users.id = transactions.recorded_by', 'left')->where('transactions.order_id', $orderId)->where('transactions.clinic_id', $clinicId ?? $this->scope->clinicId() ?? 0)->orderBy('transactions.paid_at', 'DESC')->orderBy('transactions.id', 'DESC')->get()->getResultArray();
    }

    private function refreshPaymentState(object $db, int $orderId, int $clinicId, array $order): string
    {
        $totalCents = $this->moneyCents((float) ($order['invoice_total'] ?? 0));
        $totals = $this->transactionTotals($orderId, $clinicId);
        $netPaidCents = max(0, $totals['paid_cents'] - $totals['refunded_cents']);
        $status = $this->derivedPaymentStatus($totalCents, $netPaidCents, $totals['refunded_cents'], $totals['has_failed']);
        $now = date('Y-m-d H:i:s');
        $db->table('medicine_orders')->where('id', $orderId)->where('clinic_id', $clinicId)->update(['payment_status' => $status, 'updated_at' => $now, 'last_reconciled_at' => $now]);
        if (!empty($order['invoice_id'])) $db->table('invoices')->where('id', (int) $order['invoice_id'])->where('clinic_id', $clinicId)->update(['payment_status' => $status, 'updated_at' => $now]);
        return $status;
    }

    private function derivedPaymentStatus(int $totalCents, int $netPaidCents, int $refundedCents, bool $hasFailed): string
    {
        if ($refundedCents > 0 && $netPaidCents <= 0) return 'refunded';
        if ($totalCents > 0 && $netPaidCents >= $totalCents) return 'paid';
        if ($netPaidCents > 0) return 'partially_paid';
        return $hasFailed ? 'failed' : 'unpaid';
    }

    private function paymentStatusLabel(string $status): string
    {
        return ['pending' => 'Unpaid', 'unpaid' => 'Unpaid', 'partially_paid' => 'Partially Paid', 'paid' => 'Paid', 'refunded' => 'Refunded', 'cancelled' => 'Cancelled', 'failed' => 'Failed', 'authorized' => 'Authorized'][$status] ?? ucwords(str_replace('_', ' ', $status));
    }

    private function moneyCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function paymentTimestamp(string $dateValue, string $timeValue): array
    {
        $dateValue = trim($dateValue);
        $timeValue = trim($timeValue);
        if ($dateValue === '' && $timeValue === '') {
            return [(new DateTimeImmutable('now', new \DateTimeZone((string) config('App')->appTimezone)))->format('Y-m-d H:i:s'), []];
        }
        if ($dateValue === '') return [null, ['payment_date' => 'Payment Date is required when Payment Time is provided.']];
        if ($timeValue === '') return [null, ['payment_time' => 'Payment Time is required when Payment Date is provided.']];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue)) return [null, ['payment_date' => 'Enter a valid payment date.']];
        if (!preg_match('/^\d{2}:\d{2}$/', $timeValue)) return [null, ['payment_time' => 'Enter a valid payment time.']];

        $timezone = new \DateTimeZone((string) (config('App')->appTimezone ?: date_default_timezone_get()));
        $dateTime = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $dateValue . ' ' . $timeValue, $timezone);
        $dateErrors = DateTimeImmutable::getLastErrors();
        if ($dateTime === false || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $dateTime->format('Y-m-d') !== $dateValue || $dateTime->format('H:i') !== $timeValue) {
            return [null, ['payment_date' => 'Enter a valid payment date.', 'payment_time' => 'Enter a valid payment time.']];
        }
        if ($dateTime > new DateTimeImmutable('now', $timezone)) return [null, ['payment_date' => 'Payment date and time cannot be in the future.']];

        return [$dateTime->format('Y-m-d H:i:s'), []];
    }

    private function rollbackResult(object $db, int $http, string $message, array $errors = []): array
    {
        $db->transRollback();
        return $this->result($http, $message, $errors);
    }

    private function displayDate(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value) ?: DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));

        return $date ? $date->format('d-m-Y') : '';
    }

    private function emptyPayload(string $view, array $filters, int $pageSize): array
    {
        return ['rows' => [], 'view' => $view, 'filters' => $this->displayFilters($filters), 'pagination' => ['page' => 1, 'pages' => 1, 'page_size' => $pageSize, 'total' => 0], 'readiness' => []];
    }

    private function toIso(string $value): ?string
    {
        if ($value === '') {
            return null;
        } $date = DateTimeImmutable::createFromFormat('!d-m-Y', $value);

        return $date !== false && $date->format('d-m-Y') === $value ? $date->format('Y-m-d') : null;
    }

    private function displayFilters(array $filters): array
    {
        foreach (['from', 'to'] as $key) {
            if ($filters[$key]) {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $filters[$key]);
                $filters[$key] = $date ? $date->format('d-m-Y') : '';
            }
        }

        return $filters;
    }

    private function result(int $http, string $msg, array $errors = []): array
    {
        return ['http' => $http, 'body' => ['status' => false, 'msg' => $msg] + ($errors ? ['errors' => $errors] : [])];
    }
}
