<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class EnsureCrmLeadConversionPermission extends Migration
{
    public function up()
    {
        $permission = $this->db->table('permissions')
            ->where('permission_key', 'crm.lead.convert')
            ->get()
            ->getRowArray();

        if ($permission === null) {
            $now = date('Y-m-d H:i:s');
            $this->db->table('permissions')->insert([
                'module' => 'CRM',
                'permission_name' => 'Lead Convert',
                'permission_key' => 'crm.lead.convert',
                'description' => 'CRM access: crm.lead.convert',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $permission = $this->db->table('permissions')
                ->where('permission_key', 'crm.lead.convert')
                ->get()
                ->getRowArray();
        } elseif ((int) ($permission['status'] ?? 0) !== 1) {
            $this->db->table('permissions')
                ->where('id', $permission['id'])
                ->update(['status' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
        }

        if ($permission === null) {
            return;
        }

        $roles = $this->db->table('roles')
            ->groupStart()
            ->whereIn('role_code', ['SUPER_ADMIN', 'ADMIN'])
            ->orWhereIn('role_name', ['Super Admin', 'Admin'])
            ->groupEnd()
            ->get()
            ->getResultArray();

        foreach ($roles as $role) {
            $exists = $this->db->table('role_permissions')
                ->where([
                    'role_id' => $role['id'],
                    'permission_id' => $permission['id'],
                ])
                ->countAllResults();

            if ($exists === 0) {
                $this->db->table('role_permissions')->insert([
                    'role_id' => $role['id'],
                    'permission_id' => $permission['id'],
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function down()
    {
        // Permission assignments are intentionally retained on rollback.
    }
}
