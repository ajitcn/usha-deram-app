<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPharmacyOrderPricing extends Migration
{
    public function up()
    {
        $fields = [];

        if (! $this->db->fieldExists('courier_charges', 'invoices')) {
            $fields['courier_charges'] = ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0];
        }
        if (! $this->db->fieldExists('discount_type', 'invoices')) {
            $fields['discount_type'] = ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'fixed'];
        }
        if (! $this->db->fieldExists('discount_value', 'invoices')) {
            $fields['discount_value'] = ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0];
        }
        if (! $this->db->fieldExists('pricing_updated_by', 'invoices')) {
            $fields['pricing_updated_by'] = ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true];
        }
        if (! $this->db->fieldExists('pricing_updated_at', 'invoices')) {
            $fields['pricing_updated_at'] = ['type' => 'DATETIME', 'null' => true];
        }

        if ($fields !== []) {
            $this->forge->addColumn('invoices', $fields);
        }
    }

    public function down()
    {
        foreach (['pricing_updated_at', 'pricing_updated_by', 'discount_value', 'discount_type', 'courier_charges'] as $field) {
            if ($this->db->fieldExists($field, 'invoices')) {
                $this->forge->dropColumn('invoices', $field);
            }
        }
    }
}
