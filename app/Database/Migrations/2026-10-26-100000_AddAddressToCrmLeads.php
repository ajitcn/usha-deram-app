<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddAddressToCrmLeads extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('crm_leads') && !$this->db->fieldExists('address', 'crm_leads')) {
            $this->forge->addColumn('crm_leads', ['address' => ['type' => 'TEXT', 'null' => true, 'after' => 'date_of_birth']]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('crm_leads') && $this->db->fieldExists('address', 'crm_leads')) {
            $this->forge->dropColumn('crm_leads', 'address');
        }
    }
}
