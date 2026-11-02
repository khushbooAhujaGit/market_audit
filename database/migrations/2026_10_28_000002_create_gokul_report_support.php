<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $permissions = ['Gokul Report', 'Gokul Report Map Projects'];

    // "GKL Market Survey" activity of the "Gokul DB Compliance Audit" project.
    private int $marketSurveyActivityId = 135;

    public function up(): void
    {
        Schema::create('custom_report_project_maps', function (Blueprint $table) {
            $table->id();
            $table->string('report_key', 50);
            $table->unsignedBigInteger('project_id');
            $table->timestamps();
            $table->unique(['report_key', 'project_id']);
        });

        Schema::create('custom_report_runs', function (Blueprint $table) {
            $table->id();
            $table->string('report_key', 50);
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('project_template_id');
            $table->unsignedBigInteger('row_id');
            $table->string('header_value')->nullable();
            $table->string('sub_header_value')->nullable();
            $table->string('status', 20)->default('queued'); // queued | running | done | failed
            $table->string('message')->nullable();
            $table->string('xlsx_path')->nullable();
            $table->string('zip_path')->nullable();
            $table->timestamps();
            $table->index(['report_key', 'user_id']);
        });

        $this->addOutletNameQuestion();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ($this->permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }
        $custom = Permission::where('name', 'Custom Reports')->where('guard_name', 'web')->first();
        if ($custom) {
            // Same roles that can already see the Custom Reports menu get the report itself;
            // mapping projects stays with Super Admin until it is granted deliberately.
            Role::permission($custom)->get()->each(fn (Role $role) => $role->givePermissionTo('Gokul Report'));
        }
        Role::where('name', 'Super Admin')->get()->each(fn (Role $role) => $role->givePermissionTo($this->permissions));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', $this->permissions)->where('guard_name', 'web')->delete();
        Schema::dropIfExists('custom_report_runs');
        Schema::dropIfExists('custom_report_project_maps');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function addOutletNameQuestion(): void
    {
        $activityId = $this->marketSurveyActivityId;

        $exists = DB::table('questions')->where('activity_id', $activityId)
            ->where('question', 'Name of Outlet')->whereNull('deleted_at')->exists();
        $template = DB::table('questions')->where('activity_id', $activityId)->whereNull('deleted_at')
            ->orderBy('question_sequence')->first();

        if ($exists || !$template) {
            return;
        }

        DB::transaction(function () use ($activityId, $template) {
            DB::table('questions')->where('activity_id', $activityId)->whereNull('deleted_at')
                ->increment('question_sequence');

            $row = (array) $template;
            unset($row['id']);
            $row['question']          = 'Name of Outlet';
            $row['question_type']     = 'Free Text';
            $row['question_sequence'] = 1;
            $row['help_text']         = null;
            $row['created_at']        = now();
            $row['updated_at']        = now();
            DB::table('questions')->insert($row);
        });
    }
};
