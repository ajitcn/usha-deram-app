<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMedicineDurationToPrescriptions extends Migration
{
    public function up()
    {
        $this->forge->addColumn('prescriptions', [
            'medicine_duration' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'null' => true,
                'after' => 'prescription_date',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('prescriptions', 'medicine_duration');
    }
}
