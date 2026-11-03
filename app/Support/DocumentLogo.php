<?php

namespace App\Support;

use App\Models\Employee;
use Illuminate\Support\Facades\Storage;

/** Picks the logo printed on an employee's payslips, expense statements and Form 16. */
class DocumentLogo
{
    public const OPTIONS = [
        'globalspace' => 'GlobalSpace Technologies',
        'makebot' => 'Makebot Robotics',
    ];

    private const FILES = [
        'globalspace' => 'images/globalspace-logo-light.png',
        'makebot' => 'images/makebot-logo.png',
    ];

    /**
     * Order: the logo HR chose for the employee, then the company's uploaded logo, then GlobalSpace.
     * Embedded as a data URI so dompdf needs no remote/chroot access.
     */
    public static function dataUri(?Employee $employee): ?string
    {
        $path = self::resolvePath($employee);

        if (! is_readable($path)) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
    }

    private static function resolvePath(?Employee $employee): string
    {
        if (isset(self::FILES[$employee?->document_logo])) {
            return public_path(self::FILES[$employee->document_logo]);
        }

        $companyLogo = $employee?->company?->logo_path;

        if ($companyLogo && Storage::disk('public')->exists($companyLogo)) {
            return Storage::disk('public')->path($companyLogo);
        }

        return public_path(self::FILES['globalspace']);
    }
}
