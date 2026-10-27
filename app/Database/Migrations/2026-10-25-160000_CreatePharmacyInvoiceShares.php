<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreatePharmacyInvoiceShares extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('pharmacy_invoice_shares')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'clinic_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'order_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'token_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'expires_at' => ['type' => 'DATETIME'],
            'accessed_at' => ['type' => 'DATETIME', 'null' => true],
            'revoked_at' => ['type' => 'DATETIME', 'null' => true],
            'created_by' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('token_hash');
        $this->forge->addKey(['clinic_id', 'order_id', 'expires_at']);
        $this->forge->addForeignKey('clinic_id', 'clinics', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('order_id', 'medicine_orders', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('pharmacy_invoice_shares');
    }

    public function down(): void
    {
        // Share records are revocable, but historical share audit data is retained.
    }
}
