<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $permissions = ['Custom Reports', 'Infiltration Report'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Infiltration Report used to be visible to anyone with 'Reports'; keep
        // those roles' access so nobody loses the page when it moves.
        $reports = Permission::where('name', 'Reports')->where('guard_name', 'web')->first();
        if ($reports) {
            Role::permission($reports)->get()->each(
                fn (Role $role) => $role->givePermissionTo($this->permissions)
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', $this->permissions)->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
