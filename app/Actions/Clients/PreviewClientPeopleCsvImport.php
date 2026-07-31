<?php

declare(strict_types=1);

namespace App\Actions\Clients;

use App\Models\Client;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class PreviewClientPeopleCsvImport
{
    /**
     * @param  list<array{internalCode: string, valid: bool}>  $clientRows
     * @return array{
     *   rows: list<array{line: int, clientInternalCode: string, name: string, role: string, data: array<string, string>, errors: list<string>, valid: bool}>,
     *   accepted: int,
     *   rejected: int
     * }
     */
    public function handle(User $actor, UploadedFile $file, array $clientRows): array
    {
        Gate::forUser($actor)->authorize('create', Client::class);
        if ($file->getSize() === false || $file->getSize() > 2 * 1024 * 1024) {
            throw new RuntimeException('Choose a People CSV file no larger than 2 MB.');
        }

        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            throw new RuntimeException('The People CSV could not be read. Choose it again.');
        }
        try {
            $header = fgetcsv($handle);
            if ($header === false) {
                throw new RuntimeException('The People CSV is empty.');
            }
            $columns = [];
            foreach ($header as $index => $name) {
                $columns[Str::of((string) $name)->replace("\xEF\xBB\xBF", '')->trim()->lower()->toString()] = $index;
            }
            foreach (['client_internal_code', 'person_name', 'role'] as $required) {
                if (! array_key_exists($required, $columns)) {
                    throw new RuntimeException("The People CSV header must include {$required}.");
                }
            }

            $allowedCodes = collect($clientRows)
                ->filter(static fn (array $row): bool => $row['valid'])
                ->pluck('internalCode')
                ->flip()
                ->all();
            $rows = [];
            $line = 1;
            while (($values = fgetcsv($handle)) !== false) {
                $line++;
                if (count(array_filter($values, static fn ($value): bool => trim((string) $value) !== '')) === 0) {
                    continue;
                }
                $clientInternalCode = Str::upper($this->value($values, $columns, 'client_internal_code'));
                $name = $this->value($values, $columns, 'person_name');
                $role = Str::lower($this->value($values, $columns, 'role'));
                $data = [];
                foreach (['email', 'phone', 'passport_number', 'passport_expiry_date', 'emirates_id_number', 'emirates_id_expiry_date', 'is_active'] as $field) {
                    $value = $this->value($values, $columns, $field);
                    if ($value !== '') {
                        $data[$field] = $value;
                    }
                }
                $errors = [];
                if (! isset($allowedCodes[$clientInternalCode])) {
                    $errors[] = 'Client internal code must match an accepted Clients row.';
                }
                if ($name === '' || mb_strlen($name) > 255) {
                    $errors[] = 'Person name is required and must not exceed 255 characters.';
                }
                if ($role === '' || mb_strlen($role) > 64) {
                    $errors[] = 'Role is required and must not exceed 64 characters.';
                }
                if (isset($data['email']) && filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
                    $errors[] = 'Person email must be a valid email address.';
                }
                foreach (['passport_expiry_date', 'emirates_id_expiry_date'] as $dateField) {
                    if (isset($data[$dateField]) && ! $this->validDate($data[$dateField])) {
                        $errors[] = "{$dateField} must use the YYYY-MM-DD format.";
                    }
                }
                if (isset($data['is_active']) && ! in_array(Str::lower($data['is_active']), ['yes', 'no', '1', '0', 'true', 'false'], true)) {
                    $errors[] = 'is_active must use yes or no.';
                }

                $rows[] = compact('line', 'clientInternalCode', 'name', 'role', 'data', 'errors')
                    + ['valid' => $errors === []];
            }
        } finally {
            fclose($handle);
        }

        $accepted = count(array_filter($rows, static fn (array $row): bool => $row['valid']));

        return ['rows' => $rows, 'accepted' => $accepted, 'rejected' => count($rows) - $accepted];
    }

    /** @param list<string|null> $values
     * @param  array<string, int>  $columns
     */
    private function value(array $values, array $columns, string $name): string
    {
        return trim((string) ($values[$columns[$name] ?? -1] ?? ''));
    }

    private function validDate(string $value): bool
    {
        $date = date_create_immutable($value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
