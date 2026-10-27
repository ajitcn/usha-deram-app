<?php

namespace Doctor\Controllers;

use Admin\Models\Doctor\DoctorModel;
use Admin\Models\User;
use App\Controllers\BaseController;
use App\Services\PrescriptionClarificationService;
use CodeIgniter\HTTP\ResponseInterface;

final class PrescriptionClarification extends BaseController
{
    private PrescriptionClarificationService $clarifications;

    public function __construct()
    {
        $this->clarifications = new PrescriptionClarificationService();
    }

    public function index(): string|ResponseInterface
    {
        $context = $this->context();

        if ($context === null || !$this->can('prescription.view')) {
            return redirect()->to(base_url('permission-denied'));
        }
        $pendingOnly = (string) $this->request->getGet('status') !== 'all';
        $selectedId = (int) $this->request->getGet('clarification_id');

        $selected = $selectedId > 0 ? $this->clarifications->doctorFind($selectedId, $context['doctor_id'], $context['clinic_id']) : null;
        if ($selected !== null) {
            service('audit')->record('doctor.prescription_clarification_viewed', [
                'clinic_id' => $context['clinic_id'],
                'doctor_id' => $context['doctor_id'],
                'prescription_id' => (int) $selected['prescription_id'],
                'clarification_id' => $selectedId,
            ]);
        }

        return view(DOCTOR_VIEW . 'pages/prescription_clarifications', [
            'clarifications' => $this->clarifications->doctorList($context['doctor_id'], $context['clinic_id'], $pendingOnly),
            'selected' => $selected,
            'pendingOnly' => $pendingOnly,
            'canRespond' => $this->can('prescription.edit'),
        ]);
    }

    public function respond(int $id): ResponseInterface
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->response->setStatusCode(405)->setJSON(['status' => false, 'msg' => 'Invalid request.']);
        }
        $context = $this->context();

        if ($context === null || !$this->can('prescription.edit')) {
            return $this->response->setStatusCode(403)->setJSON(['status' => false, 'msg' => 'You do not have permission to respond to prescription clarifications.']);
        }
        $result = $this->clarifications->respond($id, $context['doctor_id'], $context['clinic_id'], $this->request->getPost());

        return $this->response->setStatusCode($result['status'])->setJSON(['status' => $result['ok'], 'msg' => $result['msg'], 'data' => $result['data'] ?? []]);
    }

    private function can(string $permission): bool
    {
        return in_array($permission, session('permissions') ?? [], true);
    }

    private function context(): ?array
    {
        if (!in_array('Doctor', session('roles') ?? [], true)) {
            return null;
        }
        $user = (new User())->select('doctor_id')->where('id', (int) (session('user')['id'] ?? 0))->where('status', 1)->first();
        $doctor = (new DoctorModel())->select('id, clinic_id')->where('id', (int) ($user['doctor_id'] ?? 0))->where('status', 1)->first();

        return $doctor && (int) $doctor['clinic_id'] > 0 ? ['doctor_id' => (int) $doctor['id'], 'clinic_id' => (int) $doctor['clinic_id']] : null;
    }
}
