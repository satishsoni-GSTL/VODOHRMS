<?php

namespace App\Providers;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\BiometricDevice;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\Designation;
use App\Models\DevicePunchLog;
use App\Models\Employee;
use App\Models\EmployeeAsset;
use App\Models\EmployeeLeaveBalance;
use App\Models\EmployeeSalaryStructure;
use App\Models\EmployeeType;
use App\Models\EmploymentType;
use App\Models\ExpenseCategory;
use App\Models\FinancialYear;
use App\Models\Grade;
use App\Models\Holiday;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Models\Location;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\OnboardingChecklist;
use App\Models\PayrollInput;
use App\Models\PayrollRun;
use App\Models\PolicyDocument;
use App\Models\SalaryComponent;
use App\Models\Shift;
use App\Models\SubDepartment;
use App\Models\TaxRegimeConfig;
use App\Models\TaxRegimeSlab;
use App\Models\TaxSection;
use App\Policies\AttendanceMasterPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\ExpenseMasterPolicy;
use App\Policies\LeaveMasterPolicy;
use App\Policies\NotificationLogPolicy;
use App\Policies\NotificationTemplatePolicy;
use App\Policies\OnboardingMasterPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\PayrollMasterPolicy;
use App\Policies\PayrollRunPolicy;
use App\Policies\PolicyDocumentPolicy;
use App\Policies\TaxMasterPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(fn ($user, string $ability) => $user->hasRole('Super Admin') ? true : null);

        Gate::policy(Employee::class, EmployeePolicy::class);

        foreach ([
            Company::class, Branch::class, Location::class, Department::class,
            SubDepartment::class, Designation::class, Grade::class, CostCenter::class,
            EmployeeType::class, EmploymentType::class,
        ] as $model) {
            Gate::policy($model, OrganizationPolicy::class);
        }

        foreach ([Shift::class, Holiday::class, Attendance::class, BiometricDevice::class, DevicePunchLog::class] as $model) {
            Gate::policy($model, AttendanceMasterPolicy::class);
        }

        foreach ([LeaveType::class, LeavePolicy::class, EmployeeLeaveBalance::class] as $model) {
            Gate::policy($model, LeaveMasterPolicy::class);
        }

        Gate::policy(ExpenseCategory::class, ExpenseMasterPolicy::class);

        foreach ([SalaryComponent::class, EmployeeSalaryStructure::class, PayrollInput::class] as $model) {
            Gate::policy($model, PayrollMasterPolicy::class);
        }

        Gate::policy(PayrollRun::class, PayrollRunPolicy::class);

        foreach ([FinancialYear::class, TaxSection::class, TaxRegimeSlab::class, TaxRegimeConfig::class] as $model) {
            Gate::policy($model, TaxMasterPolicy::class);
        }

        foreach ([OnboardingChecklist::class, EmployeeAsset::class] as $model) {
            Gate::policy($model, OnboardingMasterPolicy::class);
        }

        Gate::policy(AuditLog::class, AuditLogPolicy::class);

        Gate::policy(PolicyDocument::class, PolicyDocumentPolicy::class);

        Gate::policy(NotificationTemplate::class, NotificationTemplatePolicy::class);
        Gate::policy(NotificationLog::class, NotificationLogPolicy::class);

        $this->registerNotificationLogging();
    }

    private function registerNotificationLogging(): void
    {
        Event::listen(NotificationSending::class, function (NotificationSending $event) {
            if ($event->channel !== 'mail') {
                return;
            }

            NotificationLog::create([
                'notification_id' => $event->notification->id,
                'type' => class_basename($event->notification),
                'notifiable_type' => $event->notifiable instanceof Model ? $event->notifiable::class : null,
                'notifiable_id' => $event->notifiable instanceof Model ? $event->notifiable->getKey() : null,
                'recipient_email' => $event->notifiable instanceof AnonymousNotifiable
                    ? ($event->notifiable->routes['mail'] ?? null)
                    : ($event->notifiable->email ?? null),
                'channel' => $event->channel,
                'status' => 'pending',
            ]);
        });

        Event::listen(NotificationSent::class, function (NotificationSent $event) {
            if ($event->channel !== 'mail') {
                return;
            }

            NotificationLog::where('notification_id', $event->notification->id)->update(['status' => 'sent']);
        });
    }
}
