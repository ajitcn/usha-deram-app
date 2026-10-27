<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreatePharmacyPaymentTransactions extends Migration
{
    public function up(): void
    {
        if (!$this->db->tableExists('pharmacy_payment_transactions')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
                'order_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'invoice_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
                'clinic_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'transaction_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'payment'],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'succeeded'],
                'amount' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
                'payment_method' => ['type' => 'VARCHAR', 'constraint' => 60, 'default' => 'manual'],
                'provider' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'reference_number' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'notes' => ['type' => 'TEXT', 'null' => true],
                'paid_at' => ['type' => 'DATETIME'],
                'recorded_by' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
                'idempotency_key' => ['type' => 'VARCHAR', 'constraint' => 120],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['clinic_id', 'order_id', 'paid_at']);
            $this->forge->addKey(['clinic_id', 'transaction_type', 'status']);
            $this->forge->addUniqueKey(['clinic_id', 'idempotency_key']);
            $this->forge->addForeignKey('order_id', 'medicine_orders', 'id', 'RESTRICT', 'CASCADE');
            $this->forge->addForeignKey('invoice_id', 'invoices', 'id', 'SET NULL', 'CASCADE');
            $this->forge->addForeignKey('clinic_id', 'clinics', 'id', 'RESTRICT', 'CASCADE');
            $this->forge->addForeignKey('recorded_by', 'users', 'id', 'SET NULL', 'CASCADE');
            $this->forge->createTable('pharmacy_payment_transactions');
        }

        if ($this->db->tableExists('payment_statuses')) {
            foreach ([
                ['Unpaid', 'unpaid', 'No successful payment has been recorded.', 5],
                ['Partially Paid', 'partially_paid', 'The order has received one or more partial payments.', 25],
                ['Cancelled', 'cancelled', 'Payment collection was cancelled.', 60],
            ] as [$name, $code, $description, $sortOrder]) {
                if ($this->db->table('payment_statuses')->where('status_code', $code)->get(1)->getRowArray() === null) {
                    $this->db->table('payment_statuses')->insert([
                        'status_name' => $name,
                        'status_code' => $code,
                        'description' => $description,
                        'sort_order' => $sortOrder,
                        'status' => 1,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('pharmacy_payment_transactions')) {
            $this->forge->dropTable('pharmacy_payment_transactions', true);
        }
    }
}
