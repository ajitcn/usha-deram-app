<?php

use CodeIgniter\Test\CIUnitTestCase;

final class OnlyFullGroupByQueryTest extends CIUnitTestCase
{
    public function testPharmacyDashboardAggregatesUseAggregateOnlyProjections(): void
    {
        $source = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/PharmacyDashboardService.php');

        self::assertStringContainsString("select('o.status,COUNT(DISTINCT o.id) AS total',false)", $source);
        self::assertStringContainsString("groupBy(['o.payment_provider','pp.provider_name'])", $source);
        self::assertStringNotContainsString("ordersQuery(\$clinic,\$range,\$filters)->select('o.status,COUNT(*) total')", $source);
    }

    public function testGroupedDoctorAndUserListsDoNotSelectUngroupedColumns(): void
    {
        $doctorModel = file_get_contents(ROOTPATH . 'app/Modules/Admin/Models/Doctor/DoctorModel.php');
        $accessController = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Access/Access.php');

        self::assertStringNotContainsString("groupBy('doctors.id')", $doctorModel);
        self::assertStringContainsString('WHERE dsm.doctor_id = doctors.id', $doctorModel);
        self::assertStringNotContainsString("groupBy('users.id')", $accessController);
        self::assertStringContainsString('WHERE ur.user_id = users.id', $accessController);
    }

    public function testCrmHistoryEventTypesSelectsOnlyTheGroupedColumn(): void
    {
        $source = file_get_contents(ROOTPATH . 'app/Modules/Admin/Services/CrmLeadService.php');

        self::assertStringContainsString("[], 'crm_lead_history.event_type')->groupBy('crm_lead_history.event_type')", $source);
        self::assertStringNotContainsString("historyRows(\$id,(int)\$lead['clinic_id'])->select('event_type')", $source);
    }
}
