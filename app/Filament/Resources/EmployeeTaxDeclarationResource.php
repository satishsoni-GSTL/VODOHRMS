<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ScopesToOwnTeam;
use App\Filament\Resources\EmployeeTaxDeclarationResource\Pages;
use App\Models\EmployeeTaxDeclaration;
use App\Services\TaxDeclarationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmployeeTaxDeclarationResource extends Resource
{
    protected static ?string $model = EmployeeTaxDeclaration::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Income Tax';

    protected static ?string $navigationLabel = 'Investment Declarations';

    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('employee_id')
                    ->relationship('employee', 'employee_code')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->employee_code} - {$record->full_name}")
                    ->searchable(['employee_code', 'first_name', 'last_name'])
                    ->preload()
                    ->required()
                    ->default(fn () => auth()->user()->employee_id)
                    ->disabled(fn () => ! auth()->user()->can('tax.manage'))
                    ->dehydrated(),
                Forms\Components\Select::make('financial_year_id')->relationship('financialYear', 'name')->required()->searchable()->preload(),
                Forms\Components\Select::make('tax_section_id')->relationship('taxSection', 'name')->required()->searchable()->preload(),
                Forms\Components\TextInput::make('declared_amount')->numeric()->required()->prefix('₹'),
                Forms\Components\FileUpload::make('proof_path')->directory('tax-proofs'),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('employee.employee_code')->label('Code')->searchable(),
                Tables\Columns\TextColumn::make('employee.full_name')->label('Employee')->searchable(),
                Tables\Columns\TextColumn::make('financialYear.name')->label('FY'),
                Tables\Columns\TextColumn::make('taxSection.code')->label('Section'),
                Tables\Columns\TextColumn::make('declared_amount')->money('INR'),
                Tables\Columns\TextColumn::make('eligible_amount')->money('INR')->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        EmployeeTaxDeclaration::STATUS_VERIFIED => 'success',
                        EmployeeTaxDeclaration::STATUS_REJECTED => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    EmployeeTaxDeclaration::STATUS_DECLARED => 'Declared',
                    EmployeeTaxDeclaration::STATUS_PROOF_SUBMITTED => 'Proof Submitted',
                    EmployeeTaxDeclaration::STATUS_VERIFIED => 'Verified',
                    EmployeeTaxDeclaration::STATUS_REJECTED => 'Rejected',
                ]),
                Tables\Filters\SelectFilter::make('financial_year_id')->relationship('financialYear', 'name')->label('Financial Year'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (EmployeeTaxDeclaration $record) => $record->status !== EmployeeTaxDeclaration::STATUS_VERIFIED),
                Tables\Actions\Action::make('verify')
                    ->label('Verify')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn () => auth()->user()->can('tax.verify'))
                    ->form([
                        Forms\Components\TextInput::make('approved_amount')->numeric()->required()->prefix('₹')
                            ->default(fn (EmployeeTaxDeclaration $record) => $record->declared_amount),
                        Forms\Components\Textarea::make('remarks'),
                    ])
                    ->action(function (EmployeeTaxDeclaration $record, array $data) {
                        app(TaxDeclarationService::class)->verify($record, (float) $data['approved_amount'], $data['remarks'] ?? null);
                        Notification::make()->title('Declaration verified')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn () => auth()->user()->can('tax.verify'))
                    ->form([Forms\Components\Textarea::make('remarks')->required()])
                    ->action(function (EmployeeTaxDeclaration $record, array $data) {
                        app(TaxDeclarationService::class)->reject($record, $data['remarks']);
                        Notification::make()->title('Declaration rejected')->success()->send();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return ScopesToOwnTeam::apply(parent::getEloquentQuery(), auth()->user(), 'tax.view');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployeeTaxDeclarations::route('/'),
            'create' => Pages\CreateEmployeeTaxDeclaration::route('/create'),
            'edit' => Pages\EditEmployeeTaxDeclaration::route('/{record}/edit'),
        ];
    }
}
