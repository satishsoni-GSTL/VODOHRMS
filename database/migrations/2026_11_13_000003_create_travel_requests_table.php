<?php

use App\Models\WorkflowDefinition;
use App\Models\WorkflowLevel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Travel requests (pre-approval of a trip, with an optional travel advance) and the link
 * from an expense claim to the trip it settles. The advance is netted off when the claim
 * is paid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->text('purpose');
            $table->string('travel_type')->default('domestic'); // domestic, international
            $table->string('travel_mode'); // air, train, bus, car, other
            $table->string('from_location');
            $table->string('to_location');
            $table->date('departure_date');
            $table->date('return_date');
            $table->boolean('accommodation_required')->default(false);
            $table->decimal('estimated_cost', 12, 2)->default(0);
            $table->decimal('advance_requested', 12, 2)->default(0);
            $table->decimal('advance_paid', 12, 2)->default(0);
            $table->date('advance_paid_on')->nullable();
            $table->string('advance_reference')->nullable();
            $table->foreignId('advance_paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending'); // pending, approved, rejected, sent_back, cancelled, closed
            $table->foreignId('approval_instance_id')->nullable()->constrained('approval_instances')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->text('closure_remarks')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });

        Schema::table('expense_claims', function (Blueprint $table) {
            $table->foreignId('travel_request_id')->nullable()->after('project_client')->constrained('travel_requests')->nullOnDelete();
            // Portion of the trip's advance netted off this claim when it was paid.
            $table->decimal('advance_adjusted', 12, 2)->default(0)->after('total_approved_amount');
        });

        $definition = WorkflowDefinition::firstOrCreate(
            ['module' => WorkflowDefinition::MODULE_TRAVEL],
            ['name' => 'Travel Request Approval', 'is_active' => true],
        );

        if (! $definition->levels()->exists()) {
            WorkflowLevel::create([
                'workflow_definition_id' => $definition->id,
                'sequence' => 1,
                'approver_type' => WorkflowLevel::APPROVER_REPORTING_MANAGER,
            ]);
            // Finance only signs off when a travel advance is asked for.
            WorkflowLevel::create([
                'workflow_definition_id' => $definition->id,
                'sequence' => 2,
                'approver_type' => WorkflowLevel::APPROVER_FINANCE,
                'condition_rules' => [['field' => 'advance_requested', 'operator' => '>', 'value' => 0]],
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('expense_claims', function (Blueprint $table) {
            $table->dropConstrainedForeignId('travel_request_id');
            $table->dropColumn('advance_adjusted');
        });

        Schema::dropIfExists('travel_requests');
    }
};
