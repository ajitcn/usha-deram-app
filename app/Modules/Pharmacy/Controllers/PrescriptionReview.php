<?php

namespace Pharmacy\Controllers;

use Admin\Models\MedicineModel;
use Admin\Models\PrescriptionItemModel;
use Admin\Models\PrescriptionModel;
use App\Controllers\BaseController;
use App\Services\PrescriptionClarificationService;
use CodeIgniter\HTTP\ResponseInterface;
use Pharmacy\Services\PharmacyScope;
use Pharmacy\Services\PharmacyNotificationService;
use Pharmacy\Services\PrescriptionReviewService;
use Pharmacy\Services\TransitionContract;

final class PrescriptionReview extends BaseController
{
    private const DECISIONS = ['approve' => 'approved', 'reject' => 'rejected', 'clarification' => 'clarification_requested', 'cancel' => 'cancelled', 'save_quantities' => 'save_quantities'];

    private const APPROVE_CONFIRMATION = 'I confirm this prescription is ready for fulfilment';

    private const DECISION_PERMISSIONS = ['approve' => 'pharmacy.prescription.review.approve', 'reject' => 'pharmacy.prescription.review.reject', 'clarification' => 'pharmacy.prescription.review.clarify', 'cancel' => 'pharmacy.prescription.review.cancel', 'save_quantities' => 'pharmacy.prescription.review.approve'];

    private PharmacyScope $scope;

    private PrescriptionReviewService $reviews;

    private PrescriptionModel $prescription;

    private PrescriptionItemModel $prescriptionItem;

    private MedicineModel $medicine;

    private TransitionContract $transitions;

    private PharmacyNotificationService $notifications;

    private PrescriptionClarificationService $clarifications;

    public function __construct()
    {
        $this->scope = new PharmacyScope();
        $this->reviews = new PrescriptionReviewService($this->scope);
        $this->prescription = new PrescriptionModel();
        $this->prescriptionItem = new PrescriptionItemModel();
        $this->medicine = new MedicineModel();
        $this->transitions = new TransitionContract();
        $this->notifications = new PharmacyNotificationService($this->scope);
        $this->clarifications = new PrescriptionClarificationService();
    }

    public function index(): string|ResponseInterface
    {
        $clinicId = $this->scope->clinicId();

        if ($clinicId === null || !$this->scope->can('pharmacy.prescription.review.view')) {
            return redirect()->to(base_url('permission-denied'));
        }

        try {
            $input = $this->request->getGet();
            $data = $this->reviews->list(is_array($input) ? $input : []);
            $view = in_array(($data['view'] ?? 'list'), ['list', 'grid'], true) ? $data['view'] : 'list';

            return view(PHARMACY_VIEW . 'pages/prescriptions/' . $view, array_merge($data, ['clinicId' => $clinicId]));
        } catch (\Throwable $exception) {
            log_message('error', 'Pharmacy prescription review view failed: ' . $exception->getMessage());

            return $this->response->setStatusCode(500)->setBody('Prescription review could not be loaded safely.');
        }
    }

    public function review(): ResponseInterface
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->response->setStatusCode(405)->setJSON(['status' => false, 'msg' => 'Invalid request.']);
        }
        $clinicId = $this->scope->clinicId();

        if ($clinicId === null) {
            return $this->response->setStatusCode(403)->setJSON(['status' => false, 'msg' => 'Pharmacy clinic scope is not assigned.']);
        }
        $prescriptionId = (int) $this->request->getPost('prescription_id');
        $decision = strtolower(trim((string) $this->request->getPost('decision')));
        $errors = [];

        if ($prescriptionId <= 0) {
            $errors['prescription_id'] = 'Prescription is required.';
        }

        if (!isset(self::DECISIONS[$decision])) {
            $errors['decision'] = 'Review action is invalid.';
        }

        if ($errors !== []) {
            return $this->validationError('A valid prescription and review action are required.', $errors);
        }

        if (!$this->scope->can(self::DECISION_PERMISSIONS[$decision])) {
            $requiredPermission = self::DECISION_PERMISSIONS[$decision];
            $this->scope->audit('pharmacy.permission_denied', ['permission' => $requiredPermission, 'action' => $decision, 'prescription_id' => $prescriptionId]);

            return $this->response->setStatusCode(403)->setJSON(['status' => false, 'msg' => 'You do not have permission to apply this review action.', 'errors' => ['decision' => 'Permission denied for this review action.']]);
        }
        $reason = trim((string) $this->request->getPost('reason'));
        $submittedItems = $this->request->getPost('items');
        $submittedItems = is_array($submittedItems) ? $submittedItems : [];

        $clarificationItemId = (int) $this->request->getPost('clarification_item_id');

        if (in_array($decision, ['reject', 'clarification', 'cancel'], true) && ($reason === '' || mb_strlen($reason) < 3)) {
            $errors['reason'] = 'A reason is required and must be meaningful for this review action.';
        } elseif (mb_strlen($reason) > 1000) {
            $errors['reason'] = 'Reason cannot exceed 1000 characters.';
        }

        if ($decision === 'approve' && ($this->request->getPost('approve_confirmation') !== '1' || trim((string) $this->request->getPost('approve_confirmation_text')) !== self::APPROVE_CONFIRMATION)) {
            $errors['approve_confirmation'] = 'Approval confirmation is required.';
        }

        if ($errors !== []) {
            return $this->validationError('Please correct the review fields.', $errors);
        }

        $targetStatus = self::DECISIONS[$decision];
        $prescription = $this->scope->prescription($prescriptionId, 'prescriptions.*, patients.email AS patient_email');

        if ($prescription === null) {
            return $this->response->setStatusCode(404)->setJSON(['status' => false, 'msg' => 'Prescription not found in the assigned clinic.']);
        }
        $previousStatus = (string) ($prescription['status'] ?? '');

        if ($decision === 'save_quantities') {
            $result = $this->reviews->saveDispensingQuantities($prescriptionId, $submittedItems);

            return $this->response->setStatusCode($result['http'])->setJSON($result['body']);
        }

        if ($decision === 'clarification') {
            $result = $this->clarifications->request($prescriptionId, $clinicId, (int) ($this->scope->user()['id'] ?? 0), $clarificationItemId > 0 ? $clarificationItemId : null, $reason);

            return $this->response->setStatusCode($result['status'])->setJSON(['status' => $result['ok'], 'msg' => $result['msg']]);
        }

        if ($previousStatus === 'clarification_requested') {
            return $this->response->setStatusCode(409)->setJSON(['status' => false, 'msg' => 'This prescription is waiting for the doctor response before pharmacy review can continue.', 'errors' => ['decision' => 'Resolve the clarification request first.']]);
        }

        if ($previousStatus === $targetStatus) {
            $this->auditReview($prescriptionId, $decision, $previousStatus, $targetStatus, $reason, true);

            return $this->response->setJSON(['status' => true, 'msg' => 'Prescription review was already recorded.']);
        }

        if (!$this->transitions->allows('prescription', $previousStatus, $targetStatus)) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => 'This prescription status transition is not allowed.', 'errors' => ['decision' => 'The prescription is already in a final or incompatible state.']]);
        }

        if ($targetStatus === 'approved') {
            $medicineErrors = $this->validateMedicineMaster($prescriptionId);

            if ($medicineErrors !== []) {
                return $this->validationError('Prescription cannot be approved until all medicines are active in the medicine master.', $medicineErrors);
            }
            $quantityResult = $this->reviews->saveDispensingQuantities($prescriptionId, $submittedItems);

            if ($quantityResult['http'] !== 200) {
                return $this->response->setStatusCode($quantityResult['http'])->setJSON($quantityResult['body']);
            }
        }
        $updated = $this->prescription->where('id', $prescriptionId)->where('clinic_id', $clinicId)->where('status', $previousStatus)->set(['status' => $targetStatus, 'updated_at' => date('Y-m-d H:i:s')])->update();

        if (!$updated) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => 'Prescription review could not be applied because its status changed.', 'errors' => ['decision' => 'Refresh the queue and try again.']]);
        }
        $this->auditReview($prescriptionId, $decision, $previousStatus, $targetStatus, $reason);
        $this->notifications->create($targetStatus === 'clarification_requested' ? 'prescription_clarification' : 'prescription_review', 'Prescription review updated', 'Prescription #' . $prescriptionId . ' is now ' . str_replace('_', ' ', $targetStatus) . '.', 'prescription', $prescriptionId, 'prescription-review:' . $prescriptionId . ':' . $targetStatus);
        $this->notifyPatient($prescription, $targetStatus);

        return $this->response->setJSON(['status' => true, 'msg' => 'Prescription marked as ' . str_replace('_', ' ', $targetStatus) . '.']);
    }

    public function saveQuantities(): ResponseInterface
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->response->setStatusCode(405)->setJSON(['status' => false, 'msg' => 'Invalid request.']);
        }
        if (!$this->scope->can('pharmacy.prescription.review.approve')) {
            $this->scope->audit('pharmacy.permission_denied', ['permission' => 'pharmacy.prescription.review.approve', 'action' => 'save_quantities']);

            return $this->response->setStatusCode(403)->setJSON(['status' => false, 'msg' => 'You do not have permission to edit dispensing quantities.']);
        }
        $items = $this->request->getPost('items');
        $result = $this->reviews->saveDispensingQuantities((int) $this->request->getPost('prescription_id'), is_array($items) ? $items : []);

        return $this->response->setStatusCode($result['http'])->setJSON($result['body']);
    }

    private function validationError(string $message, array $errors): ResponseInterface
    {
        return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => $message, 'errors' => $errors]);
    }

    private function auditReview(int $prescriptionId, string $decision, string $previousStatus, string $newStatus, string $reason, bool $duplicate = false): void
    {
        $this->scope->audit('pharmacy.prescription_review', ['prescription_id' => $prescriptionId, 'decision' => $decision, 'previous_status' => $previousStatus, 'new_status' => $newStatus, 'reason' => $reason, 'duplicate' => $duplicate]);
    }

    /**
     * @param array<string, mixed> $prescription
     */
    private function notifyPatient(array $prescription, string $status): void
    {
        $email = trim((string) ($prescription['patient_email'] ?? ''));

        if ($email !== '') {
            service('notification')->dispatch('email', ['address' => $email], 'prescription_review_updated', ['prescription_id' => (int) $prescription['id'], 'status' => $status], 'prescription-review:' . (int) $prescription['id'] . ':' . $status);
        }
    }

    /**
     * @return array<string, string>
     */
    private function validateMedicineMaster(int $prescriptionId): array
    {
        $items = db_connect()->table('prescription_items')
            ->select('prescription_items.id, prescription_items.medicine_id, prescription_items.medicine_name, medicines.id AS catalog_medicine_id, medicines.medicine_name AS catalog_medicine_name, medicines.status AS catalog_status')
            ->join('medicines', 'medicines.id = prescription_items.medicine_id', 'left')
            ->where('prescription_items.prescription_id', $prescriptionId)
            ->orderBy('prescription_items.id', 'ASC')
            ->get()->getResultArray();

        if ($items === []) {
            return ['items' => 'At least one prescribed medicine is required for approval.'];
        }
        $names = array_values(array_unique(array_filter(array_map(static fn (array $item): string => trim((string) ($item['medicine_name'] ?? '')), $items))));
        $activeNames = [];

        foreach ($this->medicine->select('medicine_name')->where('status', 'active')->whereIn('medicine_name', $names)->findAll() as $medicine) {
            $activeNames[strtolower(trim((string) $medicine['medicine_name']))] = true;
        }
        $errors = [];

        foreach ($items as $index => $item) {
            $name = trim((string) ($item['medicine_name'] ?? ''));
            $catalogId = (int) ($item['catalog_medicine_id'] ?? 0);
            $catalogName = trim((string) ($item['catalog_medicine_name'] ?? ''));
            $catalogStatus = strtolower(trim((string) ($item['catalog_status'] ?? '')));

            // New prescriptions are anchored to medicines.id. Legacy rows may
            // have only medicine_name, so retain the name fallback for them.
            $matchesActiveCatalog = $catalogId > 0 && $catalogStatus === 'active' && ($catalogName === '' || strcasecmp($catalogName, $name) === 0);
            $matchesLegacyName = (int) ($item['medicine_id'] ?? 0) <= 0 && $name !== '' && isset($activeNames[strtolower($name)]);

            if (!$matchesActiveCatalog && !$matchesLegacyName) {
                $errors['items.' . $index . '.medicine_name'] = 'Medicine is missing or inactive in the medicine master.';
            }
        }

        return $errors;
    }
}
