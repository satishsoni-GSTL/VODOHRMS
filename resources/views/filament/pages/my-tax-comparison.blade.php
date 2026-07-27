<x-filament-panels::page>
    @if (! $financialYear)
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            No active financial year is configured yet.
        </div>
    @else
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            @foreach (['old' => 'Old Regime', 'new' => 'New Regime'] as $key => $label)
                @php($projection = $comparison[$key])
                <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 {{ $comparison['recommended'] === $key ? 'ring-2 ring-success-500' : '' }}">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-base font-semibold">{{ $label }}</h3>
                        @if ($comparison['recommended'] === $key)
                            <span class="rounded-full bg-success-100 px-3 py-1 text-xs font-medium text-success-700">Recommended</span>
                        @endif
                    </div>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt>Projected Annual Income</dt><dd>₹{{ number_format($projection->projected_annual_income, 2) }}</dd></div>
                        <div class="flex justify-between"><dt>Exemptions / Deductions</dt><dd>₹{{ number_format($projection->total_exemptions, 2) }}</dd></div>
                        <div class="flex justify-between"><dt>Taxable Income</dt><dd>₹{{ number_format($projection->taxable_income, 2) }}</dd></div>
                        <div class="flex justify-between"><dt>Tax Before Rebate</dt><dd>₹{{ number_format($projection->tax_before_rebate, 2) }}</dd></div>
                        <div class="flex justify-between"><dt>Rebate</dt><dd>−₹{{ number_format($projection->rebate, 2) }}</dd></div>
                        <div class="flex justify-between"><dt>Surcharge</dt><dd>₹{{ number_format($projection->surcharge, 2) }}</dd></div>
                        <div class="flex justify-between"><dt>Cess</dt><dd>₹{{ number_format($projection->cess, 2) }}</dd></div>
                        <div class="flex justify-between font-semibold"><dt>Final Tax</dt><dd>₹{{ number_format($projection->final_tax, 2) }}</dd></div>
                        <div class="flex justify-between"><dt>TDS Deducted So Far</dt><dd>₹{{ number_format($projection->tds_deducted_till_date, 2) }}</dd></div>
                        <div class="flex justify-between"><dt>Remaining Tax</dt><dd>₹{{ number_format($projection->remaining_tax, 2) }}</dd></div>
                        <div class="flex justify-between font-semibold"><dt>Projected Monthly TDS</dt><dd>₹{{ number_format($projection->projected_monthly_tds, 2) }}</dd></div>
                    </dl>
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
