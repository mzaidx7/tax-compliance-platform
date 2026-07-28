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
     *     entityType: string, errors: list<string>, valid: bool
     *   }>,
     *   accepted: int,
     *   rejected: int
     * }
     */
    public function handle(User $actor, UploadedFile $file): array
    {
        Gate::forUser($actor)->authorize('create', Client::class);

        if ($file->getSize() === false || $file->getSize() > 2 * 1024 * 1024) {
            throw new RuntimeException('Choose a CSV file no larger than 2 MB.');
        }

        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            throw new RuntimeException('The CSV file could not be read. Choose the file again.');
        }

        try {
            $header = fgetcsv($handle);
            if ($header === false) {
                throw new RuntimeException('The CSV file is empty.');
            }

            $columns = $this->columns($header);
            $rows = [];
            $seenCodes = [];
            $line = 1;

            while (($values = fgetcsv($handle)) !== false) {
                $line++;
                if ($this->blank($values)) {
                    continue;
                }
                if (count($rows) >= self::MAX_ROWS) {
                    throw new RuntimeException('The CSV exceeds the 500-row release limit. Split it into smaller files.');
                }

                $row = $this->row($line, $values, $columns, $seenCodes);
                $rows[] = $row;
                if ($row['internalCode'] !== '') {
                    $seenCodes[$row['internalCode']] = true;
                }
            }
        } finally {
            fclose($handle);
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
     *   entityType: string, errors: list<string>, valid: bool
     * }
     */
    private function row(int $line, array $values, array $columns, array $seenCodes): array
    {
        $internalCode = Str::upper($this->value($values, $columns, 'internal_code'));
        $legalName = $this->value($values, $columns, 'legal_name');
        $tradeName = $this->value($values, $columns, 'trade_name');
        $entityType = $this->value($values, $columns, 'entity_type');
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

        return compact('line', 'internalCode', 'legalName', 'tradeName', 'entityType', 'errors')
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
}
