<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateConsultationFeePayments extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'appointment_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'patient_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'doctor_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'clinic_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'receipt_number' => ['type' => 'VARCHAR', 'constraint' => 50],
            'amount' => ['type' => 'DECIMAL', 'constraint' => '12,2'],
            'currency' => ['type' => 'CHAR', 'constraint' => 3, 'default' => 'INR'],
            'transaction_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'payment'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'succeeded'],
            'payment_method' => ['type' => 'VARCHAR', 'constraint' => 40],
            'reference_number' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'paid_at' => ['type' => 'DATETIME'],
            'recorded_by' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'idempotency_key' => ['type' => 'VARCHAR', 'constraint' => 100],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['appointment_id', 'clinic_id']);
        $this->forge->addKey(['appointment_id', 'status']);
        $this->forge->addUniqueKey('receipt_number');
        $this->forge->addUniqueKey(['clinic_id', 'idempotency_key']);
        $this->forge->addForeignKey('appointment_id', 'appointments', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('patient_id', 'patients', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('doctor_id', 'doctors', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('clinic_id', 'clinics', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('consultation_fee_payments');
    }

    public function down()
    {
        $this->forge->dropTable('consultation_fee_payments', true);
    }
}
