<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;

final class DoctorPrescriptionEditPolicy
{
    public const WINDOW_DAYS = 5;

    public function evaluate(?array $consultation, ?array $prescription, bool $hasOrder = false): array
    {
        $blockedMessage = 'This prescription can be edited only within 5 days of consultation completion and before pharmacy approval.';

        if ($consultation === null || $prescription === null) {
            return ['allowed' => false, 'message' => 'Prescription editing is unavailable for this consultation.', 'completion_at' => null];
        }

        if ((string) ($consultation['status'] ?? '') !== 'completed') {
            return ['allowed' => false, 'message' => 'Prescription editing is available after the consultation is completed.', 'completion_at' => null];
        }

        if (! in_array((string) ($prescription['status'] ?? ''), ['draft', 'submitted', 'clarification_requested'], true) || $hasOrder) {
            return ['allowed' => false, 'message' => $blockedMessage, 'completion_at' => null];
        }

        $completionAt = $this->completionAt($consultation);

        if ($completionAt === null) {
            return ['allowed' => false, 'message' => 'Prescription editing is unavailable because the consultation completion time is missing or invalid.', 'completion_at' => null];
        }

        $now = new DateTimeImmutable('now', $this->timezone());

        if ($completionAt > $now || $now > $completionAt->modify('+' . self::WINDOW_DAYS . ' days')) {
            return ['allowed' => false, 'message' => $blockedMessage, 'completion_at' => $completionAt];
        }

        return ['allowed' => true, 'message' => '', 'completion_at' => $completionAt];
    }

    public function completionAt(array $consultation): ?DateTimeImmutable
    {
        $timezone = $this->timezone();
        $candidates = [];

        foreach (['completed_at', 'consultation_completed_at', 'consultation_updated_at', 'updated_at'] as $field) {
            if (trim((string) ($consultation[$field] ?? '')) !== '') {
                $candidates[] = (string) $consultation[$field];
            }
        }

        $date = trim((string) ($consultation['consultation_date'] ?? ''));
        $time = trim((string) ($consultation['consultation_time'] ?? ''));

        if ($date !== '') {
            $candidates[] = $date . ' ' . ($time !== '' ? $time : '00:00:00');
        }

        foreach ($candidates as $candidate) {
            try {
                return new DateTimeImmutable($candidate, $timezone);
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function timezone(): DateTimeZone
    {
        try {
            return new DateTimeZone((string) config('App')->appTimezone);
        } catch (\Throwable) {
            return new DateTimeZone('UTC');
        }
    }
}
