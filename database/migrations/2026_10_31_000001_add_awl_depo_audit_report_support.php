<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $permissions = ['AWL Depo Audit Report', 'AWL Depo Audit Report Map Projects'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ($this->permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }
        $custom = Permission::where('name', 'Custom Reports')->where('guard_name', 'web')->first();
        if ($custom) {
            // Same roles that can already see the Custom Reports menu get the report itself;
            // mapping projects stays with Super Admin until it is granted deliberately.
            Role::permission($custom)->get()->each(fn (Role $role) => $role->givePermissionTo('AWL Depo Audit Report'));
        }
        Role::where('name', 'Super Admin')->get()->each(fn (Role $role) => $role->givePermissionTo($this->permissions));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', $this->permissions)->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
