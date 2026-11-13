<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pharma / field-force support: sales divisions, a configurable geography tree
 * (Zone > Region > Area > HQ > Territory), a field hierarchy ladder (MR > ABM > RBM > ZBM > NSM),
 * and positions (territory seats that exist whether or not anyone fills them).
 *
 * Purely additive — only new tables and nullable/defaulted columns, so existing corporate
 * data and behaviour are untouched. Nothing shows in the UI until a company has
 * `has_field_force` switched on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('has_field_force')->default(false)->after('is_active');
        });

        Schema::create('divisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('geo_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            // 1 = top of the tree (e.g. Zone); higher numbers sit below.
            $table->unsignedTinyInteger('depth')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('geographies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('geo_level_id')->constrained('geo_levels')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('geographies')->nullOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            // hq / ex_hq / outstation — drives daily allowance in most pharma TA/DA policies.
            $table->string('station_type', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('parent_id');
        });

        Schema::create('hierarchy_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_name', 20)->unique();
            // 1 = most senior (e.g. NSM); larger numbers are more junior (MR).
            $table->unsignedTinyInteger('rank');
            $table->string('workforce_type', 10)->default('field'); // field / ho / both
            // The geography level a person at this level is responsible for (MR → Territory, ABM → Area).
            $table->foreignId('geo_level_id')->nullable()->constrained('geo_levels')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('division_id')->nullable()->constrained('divisions')->nullOnDelete();
            $table->foreignId('hierarchy_level_id')->constrained('hierarchy_levels')->restrictOnDelete();
            $table->foreignId('geography_id')->nullable()->constrained('geographies')->nullOnDelete();
            $table->foreignId('parent_position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->string('code')->unique();
            $table->string('title');
            // Current incumbent; null = vacant.
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('vacant_since')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('parent_position_id');
            $table->index('employee_id');
        });

        Schema::create('position_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')->constrained('positions')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('reason', 30)->nullable(); // joining / transfer / promotion / exit / other
            $table->string('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'end_date']);
        });

        Schema::table('employees', function (Blueprint $table) {
            // null = not classified (corporate default); 'ho' = head office; 'field' = field force.
            $table->string('workforce_type', 10)->nullable()->after('employment_type_id');
            $table->foreignId('division_id')->nullable()->after('workforce_type')->constrained('divisions')->nullOnDelete();
            $table->foreignId('headquarter_id')->nullable()->after('division_id')->constrained('geographies')->nullOnDelete();
        });

        if (Schema::hasTable('permissions')) {
            foreach (['fieldforce.view', 'fieldforce.manage'] as $name) {
                DB::table('permissions')->insertOrIgnore([
                    'name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            $grants = [
                'HR Admin' => ['fieldforce.view', 'fieldforce.manage'],
                'HR Executive' => ['fieldforce.view'],
            ];

            foreach ($grants as $roleName => $permissions) {
                $roleId = DB::table('roles')->where(['name' => $roleName, 'guard_name' => 'web'])->value('id');

                if (! $roleId) {
                    continue;
                }

                foreach ($permissions as $permission) {
                    $permissionId = DB::table('permissions')->where(['name' => $permission, 'guard_name' => 'web'])->value('id');
                    DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
                }
            }

            app()['cache']->forget(config('permission.cache.key', 'spatie.permission.cache'));
        }
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('headquarter_id');
            $table->dropConstrainedForeignId('division_id');
            $table->dropColumn('workforce_type');
        });

        Schema::dropIfExists('position_assignments');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('hierarchy_levels');
        Schema::dropIfExists('geographies');
        Schema::dropIfExists('geo_levels');
        Schema::dropIfExists('divisions');

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('has_field_force');
        });

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->whereIn('name', ['fieldforce.view', 'fieldforce.manage'])->delete();
        }
    }
};
