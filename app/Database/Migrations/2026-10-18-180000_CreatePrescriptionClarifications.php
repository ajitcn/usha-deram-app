<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreatePrescriptionClarifications extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'prescription_id' => ['type' => 'INT', 'unsigned' => true],
            'prescription_item_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'clinic_id' => ['type' => 'INT', 'unsigned' => true],
            'patient_id' => ['type' => 'INT', 'unsigned' => true],
            'doctor_id' => ['type' => 'INT', 'unsigned' => true],
            'pharmacy_user_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'requested'],
            'dedupe_key' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'pharmacy_message' => ['type' => 'TEXT'],
            'doctor_response' => ['type' => 'TEXT', 'null' => true],
            'doctor_action' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'old_item_values' => ['type' => 'TEXT', 'null' => true],
            'new_item_values' => ['type' => 'TEXT', 'null' => true],
            'requested_at' => ['type' => 'DATETIME'],
            'responded_at' => ['type' => 'DATETIME', 'null' => true],
            'closed_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['clinic_id', 'doctor_id', 'status', 'requested_at']);
        $this->forge->addKey(['clinic_id', 'pharmacy_user_id', 'status', 'requested_at']);
        $this->forge->addKey(['prescription_id', 'requested_at']);
        $this->forge->addUniqueKey('dedupe_key');
        $this->forge->addForeignKey('prescription_id', 'prescriptions', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('prescription_item_id', 'prescription_items', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('clinic_id', 'clinics', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('patient_id', 'patients', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('doctor_id', 'doctors', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('pharmacy_user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('prescription_clarifications', true);

        if ($this->db->tableExists('doctor_notifications')) {
            $this->forge->addColumn('doctor_notifications', [
                'related_entity_type' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
                'related_entity_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'event_key' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            ]);
            $this->db->query('ALTER TABLE doctor_notifications ADD UNIQUE KEY doctor_notifications_event_key_unique (event_key)');
            $this->db->query('ALTER TABLE doctor_notifications ADD KEY doctor_notifications_related_entity (related_entity_type, related_entity_id)');
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('doctor_notifications')) {
            $this->db->query('ALTER TABLE doctor_notifications DROP INDEX doctor_notifications_event_key_unique');
            $this->db->query('ALTER TABLE doctor_notifications DROP INDEX doctor_notifications_related_entity');
            $this->forge->dropColumn('doctor_notifications', ['related_entity_type', 'related_entity_id', 'event_key']);
        }
        $this->forge->dropTable('prescription_clarifications', true);
    }
}
