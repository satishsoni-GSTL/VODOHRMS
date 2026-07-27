<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    private const PERMISSIONS = [
        'organization.view', 'organization.manage',
        'employee.view', 'employee.add', 'employee.edit', 'employee.delete',
        'employee.import', 'employee.export',
        'employee.view-sensitive', 'employee.edit-sensitive',
        'users.manage', 'roles.manage', 'audit.view',
        'attendance.view', 'attendance.manage', 'attendance.import',
        'leave.view', 'leave.manage',
        'expense.view', 'expense.manage', 'expense.process',
        'payroll.view', 'payroll.manage', 'payroll.process', 'payroll.finalize', 'payroll.reopen',
        'tax.view', 'tax.manage', 'tax.verify',
        'loan.view', 'loan.manage',
        'onboarding.manage',
        'resignation.view', 'resignation.manage',
        'exit.manage', 'fnf.process',
        'policy.manage',
        'notification.manage', 'notification.view',
    ];

    private const ROLE_PERMISSIONS = [
        'Super Admin' => self::PERMISSIONS, // gets everything
        'HR Admin' => [
            'organization.view', 'organization.manage',
            'employee.view', 'employee.add', 'employee.edit', 'employee.delete',
            'employee.import', 'employee.export', 'employee.view-sensitive', 'employee.edit-sensitive',
            'attendance.view', 'attendance.manage', 'attendance.import',
            'leave.view', 'leave.manage',
            'expense.view', 'expense.manage',
            'payroll.view',
            'tax.manage', 'tax.verify', 'tax.view',
            'loan.view', 'loan.manage',
            'onboarding.manage',
            'resignation.view', 'resignation.manage',
            'exit.manage', 'fnf.process',
            'policy.manage',
            'notification.manage', 'notification.view',
        ],
        'HR Executive' => [
            'organization.view',
            'employee.view', 'employee.add', 'employee.edit', 'employee.import', 'employee.export',
            'attendance.view', 'attendance.manage', 'attendance.import',
            'leave.view', 'expense.view',
            'tax.verify',
            'loan.view', 'loan.manage',
            'onboarding.manage',
            'resignation.view', 'resignation.manage',
            'exit.manage',
        ],
        'Payroll Admin' => [
            'organization.view', 'employee.view', 'employee.view-sensitive', 'employee.import', 'employee.export',
            'attendance.view', 'leave.view',
            'payroll.view', 'payroll.manage', 'payroll.process', 'payroll.finalize', 'payroll.reopen',
            'tax.view',
            'loan.view', 'loan.manage',
        ],
        'Finance Admin' => [
            'organization.view', 'employee.view', 'employee.export',
            'expense.view', 'expense.manage', 'expense.process',
            'payroll.view', 'payroll.finalize',
            'tax.view',
            'loan.view', 'loan.manage',
            'exit.manage', 'fnf.process',
        ],
        // Managers see only their own team, resolved via the reporting hierarchy — not this
        // blanket employee.view permission (that's reserved for HR-tier roles).
        'Manager' => [],
        'Employee' => [],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);
        }
    }
}
