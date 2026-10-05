<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cache ról i uprawnień
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Lista uprawnień platformy
        $permissions = [
            'users.manage',
            'roles.manage',
            'ai_settings.manage',
            'agents.manage',
            'agents.view',
            'agents.chat',
            'memory.manage',
            'memory.view',
            'integrations.manage',
            'integrations.provision',
            'telemetry.view',
            'audit.view',
            'system.update',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        // Rola: admin (wszystkie uprawnienia)
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(Permission::all());

        // Rola: operator (zarządzanie agentami, czat, pamięć, telemetria)
        $operatorRole = Role::firstOrCreate(['name' => 'operator', 'guard_name' => 'web']);
        $operatorRole->syncPermissions([
            'agents.manage',
            'agents.view',
            'agents.chat',
            'memory.manage',
            'memory.view',
            'integrations.provision',
            'telemetry.view',
        ]);

        // Rola: viewer (podgląd i czat)
        $viewerRole = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
        $viewerRole->syncPermissions([
            'agents.view',
            'agents.chat',
            'telemetry.view',
            'memory.view',
        ]);
    }
}
