<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Encodes the exact roles and default permission grid the dashboard's
 * Roles & Permissions screen displays: Super Administrator, Store Manager,
 * Sales Manager, Inventory Manager, Finance Manager — each with the module
 * access described in the original brief.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissionDefs = [
            'products' => ['view', 'create', 'edit', 'delete'],
            'orders' => ['view', 'create', 'edit', 'cancel', 'refund'],
            'customers' => ['view', 'create', 'edit', 'delete'],
            'payments' => ['view', 'verify', 'refund'],
            'inventory' => ['view', 'edit'],
            'shipping' => ['view', 'edit'],
            'reports' => ['view'],
            'administrators' => ['manage'],
            'activity_log' => ['view'],
            'settings' => ['manage'],
        ];

        foreach ($permissionDefs as $group => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(
                    ['key' => "{$group}.{$action}"],
                    ['group' => $group, 'description' => ucfirst($action).' '.str_replace('_', ' ', $group)]
                );
            }
        }

        $superAdmin = Role::firstOrCreate(
            ['name' => 'super_admin'],
            ['display_name' => 'Super Administrator', 'is_system' => true]
        );
        // Super Admin bypasses the permission table entirely via User::hasPermission(),
        // but we still attach everything so the matrix UI shows all boxes checked.
        $superAdmin->permissions()->sync(Permission::all());

        $storeManager = Role::firstOrCreate(
            ['name' => 'store_manager'],
            ['display_name' => 'Store Manager', 'is_system' => true]
        );
        $storeManager->permissions()->sync(
            Permission::whereIn('group', ['products', 'orders', 'customers', 'inventory', 'reports'])->pluck('id')
        );

        $salesManager = Role::firstOrCreate(
            ['name' => 'sales_manager'],
            ['display_name' => 'Sales Manager', 'is_system' => true]
        );
        $salesManager->permissions()->sync(
            Permission::whereIn('group', ['orders', 'customers', 'payments', 'reports'])->pluck('id')
        );

        $inventoryManager = Role::firstOrCreate(
            ['name' => 'inventory_manager'],
            ['display_name' => 'Inventory Manager', 'is_system' => true]
        );
        $inventoryManager->permissions()->sync(
            Permission::whereIn('group', ['products', 'inventory', 'shipping'])->pluck('id')
        );

        $financeManager = Role::firstOrCreate(
            ['name' => 'finance_manager'],
            ['display_name' => 'Finance Manager', 'is_system' => true]
        );
        $financeManager->permissions()->sync(
            Permission::whereIn('group', ['payments', 'orders', 'reports'])->pluck('id')
        );
    }
}
