<?php

namespace App\Filament\Resources\AttendanceResource\Pages;

use App\Exports\AttendanceTemplateExport;
use App\Filament\Resources\AttendanceResource;
use App\Imports\RawSheetReader;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Services\AttendanceImportService;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ImportAttendance extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string $resource = AttendanceResource::class;

    protected static string $view = 'filament.resources.attendance-resource.pages.import-attendance';

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->can('attendance.import') ?? false;
    }

    public ?array $uploadData = [];

    public ?int $batchId = null;

    public bool $overwriteConflicts = false;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('file')
                    ->label('Attendance Excel/CSV File')
                    ->disk('local')
                    ->directory('attendance-imports')
                    ->required(),
                Checkbox::make('overwriteConflicts')
                    ->label('Overwrite existing attendance records for the same employee/date')
                    ->helperText('Leave unchecked to skip conflicting rows (recommended). Checked rows will overwrite existing data.'),
            ])
            ->statePath('uploadData');
    }

    public function uploadAndValidate(): void
    {
        $data = $this->form->getState();
        $path = $data['file'];
        $this->overwriteConflicts = (bool) ($data['overwriteConflicts'] ?? false);

        $batch = ImportBatch::create([
            'importable_type' => ImportBatch::TYPE_ATTENDANCE,
            'file_name' => basename($path),
            'file_path' => $path,
            'uploaded_by' => auth()->id(),
            'uploaded_at' => now(),
            'status' => 'uploaded',
        ]);

        $sheets = Excel::toCollection(new RawSheetReader, Storage::disk('local')->path($path));
        $rows = $sheets->first();

        foreach ($rows as $index => $row) {
            if ($row->filter(fn ($v) => filled($v))->isEmpty()) {
                continue;
            }

            ImportRow::create([
                'batch_id' => $batch->id,
                'row_number' => $index + 2,
                'raw_data' => $row->toArray(),
                'status' => 'pending',
            ]);
        }

        app(AttendanceImportService::class)->validateBatch($batch);

        $this->batchId = $batch->id;

        Notification::make()
            ->title("Validated {$batch->refresh()->total_rows} rows: {$batch->success_rows} valid, {$batch->failed_rows} invalid")
            ->success()
            ->send();
    }

    public function confirmImport(): void
    {
        $batch = ImportBatch::findOrFail($this->batchId);

        if ($batch->success_rows === 0) {
            Notification::make()->title('No valid rows to import')->danger()->send();

            return;
        }

        $result = app(AttendanceImportService::class)->importBatch($batch, $this->overwriteConflicts);

        Notification::make()
            ->title("Imported {$result['imported']} attendance records ({$result['skipped_conflicts']} conflicts skipped)")
            ->success()
            ->send();
    }

    public function startNewImport(): void
    {
        $this->batchId = null;
        $this->form->fill();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => ImportRow::query()->where('batch_id', $this->batchId ?? 0)->orderBy('row_number'))
            ->columns([
                Tables\Columns\TextColumn::make('row_number')->label('Row'),
                Tables\Columns\TextColumn::make('raw_data.employee_code')->label('Employee Code'),
                Tables\Columns\TextColumn::make('raw_data.attendance_date')->label('Date'),
                Tables\Columns\TextColumn::make('raw_data.status')->label('Status'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'valid', 'imported' => 'success',
                        'invalid' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('errors')
                    ->label('Errors')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode('; ', $state) : '')
                    ->wrap(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'valid' => 'Valid', 'invalid' => 'Invalid', 'imported' => 'Imported',
                ]),
            ])
            ->paginated([25, 50, 100]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadTemplate')
                ->label('Download Template')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn () => Excel::download(new AttendanceTemplateExport, 'attendance-import-template.xlsx')),
            Action::make('startNew')
                ->label('Start New Import')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn () => $this->batchId !== null)
                ->action('startNewImport'),
        ];
    }

    public function getTitle(): string
    {
        return 'Bulk Import Attendance';
    }
}
