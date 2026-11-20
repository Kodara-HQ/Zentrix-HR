<?php

namespace App\Services;

use App\Enums\LetterType;
use App\Models\Company;
use App\Support\Tenant;
use App\Models\Employee;
use App\Models\FileAsset;
use App\Models\HrLetter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/** HR letters as PDFs, ported from issueLetter/drawLetter in server/src/lib/letters.ts. */
class Letters
{
    public function __construct(private Files $files) {}

    /** @param  string[]  $paragraphs */
    public function issue(Employee $employee, LetterType $type, string $subject, string $issuedOn, array $paragraphs): ?HrLetter
    {
        $company = Tenant::company();
        if (! $company) {
            return null;
        }
        $employee->loadMissing('department', 'jobTitle', 'location');

        $pdf = $this->render($company, $employee, $subject, $paragraphs, $issuedOn, $type === LetterType::APPOINTMENT);
        $file = $this->files->storeContents(
            $pdf,
            'letters/'.Str::ulid().'.pdf',
            strtolower($type->value).'-'.$employee->employee_no.'.pdf',
            'application/pdf',
            'EMPLOYEE',
            $employee->id,
            confidential: $type === LetterType::WARNING,
        );

        return HrLetter::create([
            'employee_id' => $employee->id,
            'type' => $type,
            'subject' => $subject,
            'body' => implode("\n\n", $paragraphs),
            'issued_on' => $issuedOn,
            'file_asset_id' => $file->id,
        ]);
    }

    public function render(Company $company, Employee $employee, string $subject, array $paragraphs, string $issuedOn, bool $appointment): string
    {
        return Pdf::loadView('pdf.letter', compact('company', 'employee', 'subject', 'paragraphs', 'issuedOn', 'appointment'))
            ->setPaper('a4')
            ->output();
    }
}
