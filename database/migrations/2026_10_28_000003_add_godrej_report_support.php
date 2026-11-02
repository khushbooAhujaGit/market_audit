<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        // Reverse-geocoded addresses, so a coordinate is looked up once (Nominatim allows ~1 request/second).
        Schema::create('custom_report_geocodes', function (Blueprint $table) {
            $table->id();
            $table->string('lat_lng', 40)->unique();
            $table->text('address');
            $table->timestamps();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('Godrej Report', 'web');
        $custom = Permission::where('name', 'Custom Reports')->where('guard_name', 'web')->first();
        if ($custom) {
            Role::permission($custom)->get()->each(fn (Role $role) => $role->givePermissionTo('Godrej Report'));
        }
        Role::where('name', 'Super Admin')->get()->each(fn (Role $role) => $role->givePermissionTo('Godrej Report'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_report_geocodes');
        Permission::where('name', 'Godrej Report')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
