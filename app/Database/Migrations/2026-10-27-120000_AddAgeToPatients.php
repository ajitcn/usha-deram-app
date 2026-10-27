<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAgeToPatients extends Migration
{
    public function up()
    {
        $this->forge->addColumn('patients', ['age' => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'null' => true, 'after' => 'date_of_birth']]);
    }

    public function down()
    {
        $this->forge->dropColumn('patients', 'age');
    }
}
