<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreatePasswordResetTokens extends Migration
{
    public function up(): void
    {
        if (!$this->db->tableExists('password_reset_tokens')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
                'user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'token_hash' => ['type' => 'CHAR', 'constraint' => 64],
                'expires_at' => ['type' => 'DATETIME'],
                'used_at' => ['type' => 'DATETIME', 'null' => true],
                'request_ip' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
                'attempts' => ['type' => 'INT', 'constraint' => 5, 'unsigned' => true, 'default' => 0],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('user_id');
            $this->forge->addUniqueKey('token_hash');
            $this->forge->addKey(['expires_at', 'used_at']);
            $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('password_reset_tokens');
        }

        if (!$this->db->fieldExists('password_changed_at', 'users')) {
            $this->forge->addColumn('users', ['password_changed_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'password']]);
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('password_changed_at', 'users')) {
            $this->forge->dropColumn('users', 'password_changed_at');
        }

        if ($this->db->tableExists('password_reset_tokens')) {
            $this->forge->dropTable('password_reset_tokens', true);
        }
    }
}
