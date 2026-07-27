<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeType;
use App\Models\EmploymentType;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $company = Company::firstOrCreate(
            ['code' => 'HO'],
            ['name' => 'Head Office', 'is_active' => true]
        );

        EmployeeType::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'is_active' => true]);
        EmploymentType::firstOrCreate(['code' => 'PERM'], ['name' => 'Permanent', 'is_active' => true]);
        EmploymentType::firstOrCreate(['code' => 'CONT'], ['name' => 'Contract', 'is_active' => true]);
        EmploymentType::firstOrCreate(['code' => 'PROB'], ['name' => 'Probation', 'is_active' => true]);

        $adminEmployee = Employee::firstOrCreate(
            ['employee_code' => 'ADMIN001'],
            [
                'first_name' => 'System',
                'last_name' => 'Administrator',
                'official_email' => 'admin@vodohrms.local',
                'company_id' => $company->id,
                'date_of_joining' => now(),
                'status' => Employee::STATUS_ACTIVE,
            ]
        );

        $admin = User::firstOrCreate(
            ['email' => 'admin@vodohrms.local'],
            [
                'employee_id' => $adminEmployee->id,
                'employee_code' => $adminEmployee->employee_code,
                'name' => 'System Administrator',
                'password' => bcrypt('password'),
                'is_active' => true,
            ]
        );
        $admin->syncRoles(['Super Admin']);

        $this->call(Phase2Seeder::class);
        $this->call(Phase3Seeder::class);
        $this->call(Phase4Seeder::class);
        $this->call(Phase5Seeder::class);
        $this->call(Phase6Seeder::class);
        $this->call(NotificationTemplateSeeder::class);
    }
}
