<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddDispensingQuantityToPrescriptionItems extends Migration
{
    public function up()
    {
        $fields = [];

        if (! $this->db->fieldExists('dispensing_quantity', 'prescription_items')) {
            $fields['dispensing_quantity'] = ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'quantity'];
        }
        if (! $this->db->fieldExists('dispensing_quantity_updated_by', 'prescription_items')) {
            $fields['dispensing_quantity_updated_by'] = ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'dispensing_quantity'];
        }
        if (! $this->db->fieldExists('dispensing_quantity_updated_at', 'prescription_items')) {
            $fields['dispensing_quantity_updated_at'] = ['type' => 'DATETIME', 'null' => true, 'after' => 'dispensing_quantity_updated_by'];
        }

        if ($fields !== []) {
            $this->forge->addColumn('prescription_items', $fields);
        }
    }

    public function down()
    {
        foreach (['dispensing_quantity_updated_at', 'dispensing_quantity_updated_by', 'dispensing_quantity'] as $field) {
            if ($this->db->fieldExists($field, 'prescription_items')) {
                $this->forge->dropColumn('prescription_items', $field);
            }
        }
    }
}
