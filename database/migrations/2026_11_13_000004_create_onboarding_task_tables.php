<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Onboarding tasks: HR keeps a list of task templates (who does what, how many days
 * before/after joining); each new joiner gets their own copy, assigned to real people.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_task_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            // hr, reporting_manager, employee, role, user
            $table->string('assignee_type');
            $table->unsignedBigInteger('role_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Due relative to the date of joining; negative = before joining.
            $table->smallInteger('due_offset_days')->default(0);
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('onboarding_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('onboarding_task_templates')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('assignee_type');
            // Resolved owner: one user, or everyone holding a role (e.g. HR Admin).
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->date('due_date');
            $table->string('status')->default('pending'); // pending, done, skipped
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->timestamps();

            $table->index(['status', 'due_date']);
            $table->index(['assigned_user_id', 'status']);
        });

        if (DB::table('onboarding_task_templates')->doesntExist()) {
            $now = now();
            $templates = [
                ['Send offer and appointment letter', 'hr', -7, 'Share the signed appointment letter and joining instructions.'],
                ['Create email and system accounts', 'hr', -2, 'IT: official email, HRMS login, and access to required systems. Reassign this template to your IT owner.'],
                ['Arrange seating and ID / access card', 'hr', -1, 'Admin: desk, ID card and building access. Reassign this template to your Admin owner.'],
                ['Issue laptop and other assets', 'hr', 0, 'Record the issued assets under Employee Assets.'],
                ['Collect joining documents', 'hr', 0, 'ID & address proof, education and experience certificates, photographs, PAN, bank details.'],
                ['Welcome and team introduction', 'reporting_manager', 0, 'Introduce the new joiner to the team and walk them through their role.'],
                ['Complete your profile and upload documents', 'employee', 1, 'Fill in personal, address and emergency details in your profile and upload your documents.'],
                ['HR induction and policy walkthrough', 'hr', 1, 'Leave, attendance, expense and code-of-conduct policies.'],
                ['Read and acknowledge company policies', 'employee', 3, 'Policies are under Policies in the HRMS.'],
                ['Add to payroll and statutory (PF / ESIC)', 'hr', 7, 'Salary structure, bank details, UAN / ESIC registration.'],
                ['Agree 30-60-90 day goals', 'reporting_manager', 7, 'Set and share expectations for the probation period.'],
                ['30-day check-in', 'reporting_manager', 30, 'How is the new joiner settling in? Flag any concerns to HR.'],
            ];

            foreach ($templates as $i => [$title, $type, $offset, $description]) {
                DB::table('onboarding_task_templates')->insert([
                    'title' => $title,
                    'description' => $description,
                    'assignee_type' => $type,
                    'due_offset_days' => $offset,
                    'sequence' => ($i + 1) * 10,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_tasks');
        Schema::dropIfExists('onboarding_task_templates');
    }
};
