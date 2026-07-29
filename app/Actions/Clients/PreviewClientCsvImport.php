<?php

declare(strict_types=1);

namespace App\Actions\Clients;

use App\Models\Client;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class PreviewClientCsvImport
{
    public const MAX_ROWS = 500;

    /**
     * @return array{
     *   rows: list<array{
     *     line: int, internalCode: string, legalName: string, tradeName: string,
     *     entityType: string, masterData: array<string, string>, errors: list<string>, valid: bool
     *   }>,
     *   accepted: int,
     *   rejected: int
     * }
     */
    public function handle(User $actor, UploadedFile $file): array
    {
        Gate::forUser($actor)->authorize('create', Client::class);

        if ($file->getSize() === false || $file->getSize() > 2 * 1024 * 1024) {
            throw new RuntimeException('Choose a CSV or Excel file no larger than 2 MB.');
        }

        $records = $this->records($file);
        $header = array_shift($records);
        if ($header === null) {
            throw new RuntimeException('The import file is empty.');
        }

        $columns = $this->columns($header);
        $rows = [];
        $seenCodes = [];
        foreach ($records as $offset => $values) {
            $line = $offset + 2;
            if ($this->blank($values)) {
                continue;
            }
            if (count($rows) >= self::MAX_ROWS) {
                throw new RuntimeException('The import file exceeds the 500-row release limit. Split it into smaller files.');
            }

            $row = $this->row($line, $values, $columns, $seenCodes);
            $rows[] = $row;
            if ($row['internalCode'] !== '') {
                $seenCodes[$row['internalCode']] = true;
            }
        }

        if ($rows === []) {
            throw new RuntimeException('The CSV contains no client rows.');
        }

        $accepted = count(array_filter($rows, static fn (array $row): bool => $row['valid']));

        return [
            'rows' => $rows,
            'accepted' => $accepted,
            'rejected' => count($rows) - $accepted,
        ];
    }

    /**
     * @param  list<string|null>  $header
     * @return array<string, int>
     */
    private function columns(array $header): array
    {
        $columns = [];
        foreach ($header as $index => $name) {
            $normalized = Str::of((string) $name)->replace("\xEF\xBB\xBF", '')->trim()->lower()->toString();
            $columns[$normalized] = $index;
        }

        foreach (['internal_code', 'legal_name'] as $required) {
            if (! array_key_exists($required, $columns)) {
                throw new RuntimeException("The CSV header must include {$required}.");
            }
        }

        return $columns;
    }

    /**
     * @param  list<string|null>  $values
     * @param  array<string, int>  $columns
     * @param  array<string, true>  $seenCodes
     * @return array{
     *   line: int, internalCode: string, legalName: string, tradeName: string,
     *   entityType: string, masterData: array<string, string>, errors: list<string>, valid: bool
     * }
     */
    private function row(int $line, array $values, array $columns, array $seenCodes): array
    {
        $internalCode = Str::upper($this->value($values, $columns, 'internal_code'));
        $legalName = $this->value($values, $columns, 'legal_name');
        $tradeName = $this->value($values, $columns, 'trade_name');
        $entityType = $this->value($values, $columns, 'entity_type');
        $masterData = [];
        foreach ([
            'email', 'mobile', 'vat_trn', 'ct_trn', 'vat_frequency', 'vat_period_start', 'vat_period_end',
            'ct_period_start', 'ct_period_end', 'trade_license_number', 'trade_license_authority',
            'trade_license_issue_date', 'trade_license_expiry_date', 'passport_number', 'passport_expiry_date',
            'emirates_id_number', 'emirates_id_expiry_date', 'authorised_signatory_name',
            'authorised_signatory_passport_number', 'authorised_signatory_passport_expiry_date',
            'authorised_signatory_emirates_id_number', 'authorised_signatory_emirates_id_expiry_date',
        ] as $field) {
            $value = $this->value($values, $columns, $field);
            if ($value !== '') {
                $masterData[$field] = $value;
            }
        }
        $errors = [];

        if ($internalCode === '') {
            $errors[] = 'Internal code is required.';
        } elseif (strlen($internalCode) > 64 || preg_match('/^[A-Z0-9][A-Z0-9._\/-]*$/', $internalCode) !== 1) {
            $errors[] = 'Internal code must use up to 64 letters, numbers, dots, slashes, hyphens or underscores.';
        } elseif (isset($seenCodes[$internalCode])) {
            $errors[] = 'Internal code is duplicated in this file.';
        } elseif (Client::query()->where('internal_code_normalized', $internalCode)->exists()) {
            $errors[] = 'Internal code already exists in this firm.';
        }

        if ($legalName === '') {
            $errors[] = 'Legal name is required.';
        } elseif (mb_strlen($legalName) > 255) {
            $errors[] = 'Legal name must not exceed 255 characters.';
        }
        if (mb_strlen($tradeName) > 255) {
            $errors[] = 'Trade name must not exceed 255 characters.';
        }
        if (mb_strlen($entityType) > 100) {
            $errors[] = 'Entity type must not exceed 100 characters.';
        }
        if (isset($masterData['email']) && filter_var($masterData['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Email must be a valid email address.';
        }
        if (isset($masterData['mobile']) && preg_match('/^[0-9+() .-]{5,32}$/', $masterData['mobile']) !== 1) {
            $errors[] = 'Mobile number contains unsupported characters.';
        }
        foreach ([
            'vat_period_start', 'vat_period_end', 'ct_period_start', 'ct_period_end',
            'trade_license_issue_date', 'trade_license_expiry_date', 'passport_expiry_date',
            'emirates_id_expiry_date', 'authorised_signatory_passport_expiry_date',
            'authorised_signatory_emirates_id_expiry_date',
        ] as $dateField) {
            if (isset($masterData[$dateField]) && $this->parseDate($masterData[$dateField]) === null) {
                $errors[] = "{$dateField} must use the YYYY-MM-DD format.";
            }
        }
        if (isset($masterData['vat_period_start']) xor isset($masterData['vat_period_end'])) {
            $errors[] = 'VAT period start and end must be supplied together.';
        }
        if (isset($masterData['ct_period_start']) xor isset($masterData['ct_period_end'])) {
            $errors[] = 'Corporate Tax period start and end must be supplied together.';
        }
        if (isset($masterData['vat_period_start'], $masterData['vat_period_end'])
            && $masterData['vat_period_start'] > $masterData['vat_period_end']) {
            $errors[] = 'VAT period end must be on or after its start.';
        }
        if (isset($masterData['ct_period_start'], $masterData['ct_period_end'])
            && $masterData['ct_period_start'] > $masterData['ct_period_end']) {
            $errors[] = 'Corporate Tax period end must be on or after its start.';
        }
        if (isset($masterData['ct_period_start'], $masterData['ct_period_end'])
            && $masterData['ct_period_start'] <= $masterData['ct_period_end']) {
            $periodStart = date_create_immutable($masterData['ct_period_start']);
            $periodEnd = date_create_immutable($masterData['ct_period_end']);
            if ($periodStart !== false && $periodEnd !== false) {
                $months = (($periodEnd->format('Y') - $periodStart->format('Y')) * 12)
                    + ((int) $periodEnd->format('n') - (int) $periodStart->format('n')) + 1;
                if ($months < 6 || $months > 18) {
                    $errors[] = 'The first Corporate Tax period should normally be between 6 and 18 months. Confirm the period with an administrator before importing it.';
                }
            }
        }

        return compact('line', 'internalCode', 'legalName', 'tradeName', 'entityType', 'masterData', 'errors')
            + ['valid' => $errors === []];
    }

    /**
     * @param  list<string|null>  $values
     * @param  array<string, int>  $columns
     */
    private function value(array $values, array $columns, string $name): string
    {
        $index = $columns[$name] ?? null;

        return $index === null ? '' : trim((string) ($values[$index] ?? ''));
    }

    /** @param list<string|null> $values */
    private function blank(array $values): bool
    {
        return count(array_filter($values, static fn (?string $value): bool => trim((string) $value) !== '')) === 0;
    }

    private function parseDate(string $value): ?string
    {
        $date = date_create_immutable($value);

        return $date !== false && $date->format('Y-m-d') === $value ? $value : null;
    }

    /** @return list<list<string|null>> */
    private function records(UploadedFile $file): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if ($extension === 'xlsx') {
            return $this->xlsxRecords($file->getRealPath());
        }

        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            throw new RuntimeException('The import file could not be read. Choose it again.');
        }

        try {
            $records = [];
            while (($values = fgetcsv($handle)) !== false) {
                $records[] = $values;
            }

            return $records;
        } finally {
            fclose($handle);
        }
    }

    /** @return list<list<string|null>> */
    private function xlsxRecords(string $path): array
    {
        $archive = new \ZipArchive;
        if ($archive->open($path) !== true) {
            throw new RuntimeException('The Excel file could not be opened. Save it as .xlsx and try again.');
        }

        try {
            $sheet = $archive->getFromName('xl/worksheets/sheet1.xml');
            if ($sheet === false) {
                throw new RuntimeException('The Excel file does not contain a readable first worksheet.');
            }
            $shared = [];
            $sharedXml = $archive->getFromName('xl/sharedStrings.xml');
            if ($sharedXml !== false) {
                $sharedRoot = simplexml_load_string($sharedXml);
                if ($sharedRoot !== false) {
                    foreach ($sharedRoot->si as $item) {
                        $shared[] = trim(implode('', array_map('strval', iterator_to_array($item->t))));
                    }
                }
            }

            $root = simplexml_load_string($sheet);
            if ($root === false) {
                throw new RuntimeException('The first Excel worksheet is not valid XML.');
            }
            $records = [];
            foreach ($root->sheetData->row as $row) {
                $values = [];
                foreach ($row->c as $cell) {
                    $reference = (string) $cell['r'];
                    preg_match('/^([A-Z]+)/', $reference, $matches);
                    $index = $this->columnIndex($matches[1] ?? 'A');
                    $value = isset($cell->v) ? (string) $cell->v : (string) ($cell->is->t ?? '');
                    if ((string) $cell['t'] === 's' && isset($shared[(int) $value])) {
                        $value = $shared[(int) $value];
                    }
                    $values[$index] = $value;
                }
                ksort($values);
                $records[] = array_values($values);
            }

            return $records;
        } finally {
            $archive->close();
        }
    }

    private function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + ord($letter) - 64;
        }

        return max(0, $index - 1);
    }
}
