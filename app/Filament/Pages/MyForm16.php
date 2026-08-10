<?php

namespace App\Filament\Pages;

use App\Models\Form16;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class MyForm16 extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationLabel = 'My Form 16';

    protected static ?string $navigationGroup = 'Income Tax';

    protected static string $view = 'filament.pages.my-form16';

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) auth()->user()?->employee_id;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Form16::query()->where('employee_id', auth()->user()->employee_id))
            ->columns([
                Tables\Columns\TextColumn::make('financialYear.name')->label('Financial Year')->sortable(),
                Tables\Columns\TextColumn::make('regime')->badge()->formatStateUsing(fn (string $state) => ucfirst($state)),
                Tables\Columns\TextColumn::make('generated_at')->dateTime(),
            ])
            ->defaultSort('financial_year_id', 'desc')
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Form16 $record) => route('form16.download', $record))
                    ->openUrlInNewTab(),
            ]);
    }
}
