<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class Help extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $navigationLabel = 'Help / User Guide';

    protected static ?int $navigationSort = 100;

    protected static string $view = 'filament.pages.help';

    protected static ?string $title = 'Help & User Guide';

    public string $contentHtml = '';

    public function mount(): void
    {
        $path = base_path('docs/USER_GUIDE.md');

        $markdown = File::exists($path)
            ? File::get($path)
            : "# Help\n\nThe user guide file could not be found at `docs/USER_GUIDE.md`.";

        $this->contentHtml = Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}
