<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddCompletedAtToConsultations extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('completed_at', 'consultations')) {
            $this->forge->addColumn('consultations', [
                'completed_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'status'],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('completed_at', 'consultations')) {
            $this->forge->dropColumn('consultations', 'completed_at');
        }
    }
}
