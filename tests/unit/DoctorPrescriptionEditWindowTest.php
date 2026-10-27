<?php

namespace Tests\Unit;

use App\Services\DoctorPrescriptionEditPolicy;
use CodeIgniter\Test\CIUnitTestCase;
use DateTimeImmutable;

final class DoctorPrescriptionEditWindowTest extends CIUnitTestCase
{
    public function testCompletedPrescriptionWithinFiveDaysIsEditable(): void
    {
        $policy = new DoctorPrescriptionEditPolicy();
        $completion = (new DateTimeImmutable('now'))->modify('-4 days')->format('Y-m-d H:i:s');

        $decision = $policy->evaluate(
            ['status' => 'completed', 'updated_at' => $completion],
            ['status' => 'submitted'],
        );

        $this->assertTrue($decision['allowed']);
    }

    public function testCompletedPrescriptionOlderThanFiveDaysIsBlocked(): void
    {
        $policy = new DoctorPrescriptionEditPolicy();
        $completion = (new DateTimeImmutable('now'))->modify('-6 days')->format('Y-m-d H:i:s');

        $decision = $policy->evaluate(
            ['status' => 'completed', 'updated_at' => $completion],
            ['status' => 'submitted'],
        );

        $this->assertFalse($decision['allowed']);
        $this->assertStringContainsString('within 5 days', $decision['message']);
    }

    public function testApprovedAndOrderedPrescriptionsAreBlockedWithinWindow(): void
    {
        $policy = new DoctorPrescriptionEditPolicy();
        $consultation = ['status' => 'completed', 'updated_at' => (new DateTimeImmutable('now'))->modify('-1 day')->format('Y-m-d H:i:s')];

        $this->assertFalse($policy->evaluate($consultation, ['status' => 'approved'])['allowed']);
        $this->assertFalse($policy->evaluate($consultation, ['status' => 'submitted'], true)['allowed']);
    }

    public function testMissingOrInvalidCompletionTimeIsBlockedSafely(): void
    {
        $policy = new DoctorPrescriptionEditPolicy();

        $missing = $policy->evaluate(['status' => 'completed'], ['status' => 'submitted']);
        $invalid = $policy->evaluate(['status' => 'completed', 'updated_at' => 'not-a-date'], ['status' => 'submitted']);

        $this->assertFalse($missing['allowed']);
        $this->assertFalse($invalid['allowed']);
        $this->assertStringContainsString('missing or invalid', $missing['message']);
    }
}
