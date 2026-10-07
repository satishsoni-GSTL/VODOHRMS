<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\ExpenseClaim;
use App\Models\User;
use App\Services\ExpenseStatementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\Phase3Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseStatementTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(Phase3Seeder::class);
        Storage::fake('local');

        $company = Company::create(['code' => 'HO', 'name' => 'Head Office', 'is_active' => true]);
        $this->manager = Employee::create(['employee_code' => 'MGR', 'first_name' => 'Maya', 'official_email' => 'mgr@x.test', 'company_id' => $company->id, 'date_of_joining' => '2024-01-01', 'status' => Employee::STATUS_ACTIVE]);
        $this->employee = Employee::create(['employee_code' => 'EMP', 'first_name' => 'Ravi', 'official_email' => 'emp@x.test', 'company_id' => $company->id, 'date_of_joining' => '2024-01-01', 'status' => Employee::STATUS_ACTIVE, 'reporting_manager_id' => $this->manager->id]);
    }

    private function jpeg(int $w, int $h): string
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 240, 200, 120));
        ob_start();
        imagejpeg($img);

        return (string) ob_get_clean();
    }

    private function png(): string
    {
        $img = imagecreatetruecolor(300, 500);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 128, 128, 60));
        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }

    private function twoPagePdf(): string
    {
        return Pdf::loadHTML('<p>Hotel invoice page 1</p><div style="page-break-after: always"></div><p>Page 2</p>')->output();
    }

    private function claimWith(array $lines, string $status = ExpenseClaim::STATUS_APPROVED): ExpenseClaim
    {
        $claim = ExpenseClaim::create([
            'claim_number' => 'EXP-'.uniqid(), 'employee_id' => $this->employee->id, 'claim_date' => '2026-09-30', 'status' => $status,
        ]);
        $category = ExpenseCategory::query()->firstOrFail();

        foreach ($lines as [$date, $amount, $file, $contents]) {
            if ($file && $contents !== null) {
                Storage::disk('local')->put($file, $contents);
            }
            $claim->lines()->create(['category_id' => $category->id, 'expense_date' => $date, 'requested_amount' => $amount, 'receipt_path' => $file, 'vendor' => 'Vendor']);
        }

        return $claim;
    }

    private function pageCount(string $pdf): int
    {
        return preg_match_all('/\/Type\s*\/Page[^s]/', $pdf);
    }

    public function test_statement_is_one_pdf_with_summary_and_every_bill(): void
    {
        $this->claimWith([
            ['2026-09-02', 450.50, 'expense-receipts/taxi.jpg', $this->jpeg(1200, 1600)],   // A1 portrait photo → 1 page
            ['2026-09-05', 1200, 'expense-receipts/wide.jpeg', $this->jpeg(1600, 900)],      // A2 landscape photo → 1 page
            ['2026-09-07', 3000, 'expense-receipts/hotel.pdf', $this->twoPagePdf()],         // A3 PDF → 2 pages
            ['2026-09-08', 99, 'expense-receipts/logo.png', $this->png()],                   // A4 transparent PNG → 1 page
            ['2026-09-09', 50, 'expense-receipts/sheet.xlsx', 'not a picture'],              // A5 unsupported → placeholder
            ['2026-09-10', 75, 'expense-receipts/gone.jpg', null],                           // A6 file missing → placeholder
            ['2026-09-11', 20, null, null],                                                  // no bill
            ['2026-10-01', 999, 'expense-receipts/next-month.jpg', $this->jpeg(100, 100)],   // other month — excluded
        ]);

        $service = app(ExpenseStatementService::class);
        $pdf = $service->build($this->employee, '2026-09');

        $this->assertStringStartsWith('%PDF', $pdf);
        $summaryPages = $this->pageCount(Pdf::loadView('reports.expense-consolidated', [
            'employee' => $this->employee, 'monthLabel' => 'x', 'generatedAt' => 'x', 'logo' => null, 'lines' => collect(),
            'attachmentCount' => 0, 'byCategory' => [], 'totalRequested' => 0, 'totalApproved' => 0,
        ])->output());
        // summary (≥1) + 1 + 1 + 2 + 1 + 1 + 1 bill pages
        $this->assertGreaterThanOrEqual($summaryPages + 7, $this->pageCount($pdf));
        $this->assertSame('expense-statement-EMP-2026-09.pdf', $service->fileName($this->employee, '2026-09'));
    }

    public function test_bills_must_be_a_photo_or_pdf_no_zip(): void
    {
        $user = User::create(['employee_id' => $this->employee->id, 'employee_code' => 'EMP', 'name' => 'Ravi', 'email' => 'emp@x.test', 'password' => bcrypt('x'), 'is_active' => true]);
        $user->assignRole('Employee');
        $category = ExpenseCategory::query()->firstOrFail();
        $line = fn (array $extra) => ['category_id' => $category->id, 'expense_date' => '2026-09-02', 'requested_amount' => 100] + $extra;

        // Mobile app API.
        $token = $this->postJson('/api/mobile/login', ['login' => 'EMP', 'password' => 'x'])->json('token');
        foreach (['bills.zip', 'bills.xlsx', 'bill.docx'] as $name) {
            $this->withToken($token)->post('/api/mobile/expenses', [
                'claim_date' => '2026-09-02',
                'lines' => [$line(['receipt' => \Illuminate\Http\UploadedFile::fake()->create($name, 20)])],
            ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('lines.0.receipt');
        }
        $this->assertSame(0, ExpenseClaim::count());

        // Web form: the bill upload only accepts photos and PDFs.
        $this->actingAs($user);
        $page = \Livewire\Livewire::test(\App\Filament\Resources\ExpenseClaimResource\Pages\CreateExpenseClaim::class)->instance();
        $repeater = collect($page->form->getFlatComponents(withHidden: true))->first(fn ($c) => $c instanceof \Filament\Forms\Components\Repeater);
        $upload = collect($repeater->getChildComponentContainer()->getFlatComponents(withHidden: true))
            ->first(fn ($c) => $c instanceof \Filament\Forms\Components\FileUpload);
        $this->assertSame(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], $upload->getAcceptedFileTypes());
        $this->assertNotContains('application/zip', $upload->getAcceptedFileTypes());
    }

    public function test_access_rules_and_downloads(): void
    {
        $this->claimWith([['2026-09-02', 100, 'expense-receipts/a.jpg', $this->jpeg(200, 300)]]);

        $makeUser = function (Employee $e, string $role) {
            $u = User::create(['employee_id' => $e->id, 'employee_code' => $e->employee_code, 'name' => $e->first_name, 'email' => $e->official_email, 'password' => bcrypt('x'), 'is_active' => true]);
            $u->assignRole($role);

            return $u;
        };
        $managerUser = $makeUser($this->manager, 'Manager');
        $employeeUser = $makeUser($this->employee, 'Employee');

        // Manager: their report's PDF, and the month ZIP.
        $this->actingAs($managerUser)->get("/reports/expense-statement/{$this->employee->id}?month=2026-09")
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($managerUser)->get('/reports/expense-statements?month=2026-09')->assertOk();

        // Employee: not their manager's statement, nor the all-staff ZIP.
        $this->actingAs($employeeUser)->get("/reports/expense-statement/{$this->manager->id}?month=2026-09")->assertForbidden();
        $this->actingAs($employeeUser)->get('/reports/expense-statements?month=2026-09')->assertForbidden();

        // Employee's own statement from the mobile app.
        $token = $this->postJson('/api/mobile/login', ['login' => 'EMP', 'password' => 'x'])->json('token');
        $this->withToken($token)->get('/api/mobile/expenses/statement?month=2026-09')
            ->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="expense-statement-EMP-2026-09.pdf"');
    }
}
