<?php

namespace App\Filament\Resources\ExpenseClaimResource\Pages;

use App\Filament\Resources\ExpenseClaimResource;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewExpenseClaim extends ViewRecord
{
    protected static string $resource = ExpenseClaimResource::class;

    protected function getHeaderActions(): array
    {
        return ExpenseClaimResource::approvalHeaderActions();
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('claim_number'),
            TextEntry::make('employee.full_name')->label('Employee'),
            TextEntry::make('claim_date')->date(),
            TextEntry::make('project_client'),
            TextEntry::make('status')->badge(),
            TextEntry::make('total_requested_amount')->money('INR'),
            TextEntry::make('total_approved_amount')->money('INR'),
            RepeatableEntry::make('lines')
                ->schema([
                    TextEntry::make('category.name')->label('Category'),
                    TextEntry::make('expense_date')->date(),
                    TextEntry::make('requested_amount')->money('INR'),
                    TextEntry::make('approved_amount')->money('INR'),
                    TextEntry::make('vendor'),
                    TextEntry::make('receipt_path')
                        ->label('Receipt')
                        ->formatStateUsing(fn (?string $state) => $state ? 'Download Receipt' : 'No receipt')
                        ->url(fn ($record) => $record->receipt_path ? route('expense-receipts.download', $record) : null)
                        ->openUrlInNewTab()
                        ->color(fn (?string $state) => $state ? 'primary' : 'gray'),
                    TextEntry::make('description')->columnSpanFull(),
                ])
                ->columns(3)
                ->columnSpanFull(),
        ])->columns(2);
    }
}
