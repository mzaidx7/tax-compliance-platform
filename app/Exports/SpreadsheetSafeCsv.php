<?php

declare(strict_types=1);

namespace App\Exports;

use App\Data\CsvWriteResult;
use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;
use RuntimeException;

final readonly class SpreadsheetSafeCsv
{
    public function __construct(private Repository $config) {}

    /**
     * @param  resource  $stream
     * @param  array<array-key, mixed>  $headers
     * @param  iterable<array-key, mixed>  $rows
     */
    public function write($stream, array $headers, iterable $rows): CsvWriteResult
    {
        if (! is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new InvalidArgumentException('CSV output requires a writable stream resource.');
        }

        $columnCount = count($headers);

        if (! array_is_list($headers) || $columnCount < 1 || $columnCount > $this->limit('max_columns')) {
            throw new InvalidArgumentException('CSV headers must be a non-empty bounded list.');
        }

        $normalizedHeaders = [];

        foreach ($headers as $header) {
            if (! is_string($header) || $header === '') {
                throw new InvalidArgumentException('CSV headers must be non-empty strings.');
            }

            $normalizedHeaders[] = $header;
        }

        if (count(array_unique($normalizedHeaders)) !== $columnCount) {
            throw new InvalidArgumentException('CSV headers must be unique.');
        }

        $neutralizedCellCount = 0;
        $this->writeRow(
            $stream,
            array_map(
                fn (string $header): string => $this->normalizeStringCell($header, $neutralizedCellCount),
                $normalizedHeaders,
            ),
        );
        $this->assertByteLimit($stream);

        $rowCount = 0;

        foreach ($rows as $row) {
            $rowCount++;

            if ($rowCount > $this->limit('max_rows')) {
                throw new InvalidArgumentException('The CSV row limit was exceeded.');
            }

            if (! is_array($row) || ! array_is_list($row) || count($row) !== $columnCount) {
                throw new InvalidArgumentException("CSV row {$rowCount} does not match the header shape.");
            }

            $normalized = [];

            foreach ($row as $cell) {
                $normalized[] = $this->normalizeCell($cell, $neutralizedCellCount);
            }

            $this->writeRow($stream, $normalized);
            $this->assertByteLimit($stream);
        }

        $bytes = ftell($stream);

        if (! is_int($bytes)) {
            throw new RuntimeException('The CSV byte count could not be determined.');
        }

        return new CsvWriteResult(
            rowCount: $rowCount,
            columnCount: $columnCount,
            neutralizedCellCount: $neutralizedCellCount,
            bytes: $bytes,
        );
    }

    private function normalizeCell(mixed $value, int &$neutralizedCellCount): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            if (! is_finite($value)) {
                throw new InvalidArgumentException('CSV cells cannot contain non-finite numbers.');
            }

            return (string) $value;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('CSV cells must be scalar values or null.');
        }

        return $this->normalizeStringCell($value, $neutralizedCellCount);
    }

    private function normalizeStringCell(string $value, int &$neutralizedCellCount): string
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            throw new InvalidArgumentException('CSV cells must contain valid UTF-8.');
        }

        if (str_contains($value, "\0")) {
            throw new InvalidArgumentException('CSV cells cannot contain null bytes.');
        }

        $cellLength = mb_strlen($value, 'UTF-8');
        $cellLengthLimit = $this->limit('max_cell_characters');

        if ($cellLength > $cellLengthLimit) {
            throw new InvalidArgumentException('The CSV cell length limit was exceeded.');
        }

        $formulaCandidate = ltrim($value, ' ');

        if (preg_match('/\A[=+\-@\t\r\n\x{FF1D}\x{FF0B}\x{FF0D}\x{FF20}]/u', $formulaCandidate) === 1) {
            if ($cellLength + 1 > $cellLengthLimit) {
                throw new InvalidArgumentException('The neutralized CSV cell length limit was exceeded.');
            }

            $neutralizedCellCount++;

            return "\t{$value}";
        }

        return $value;
    }

    /**
     * @param  resource  $stream
     * @param  list<string>  $cells
     */
    private function writeRow($stream, array $cells): void
    {
        if (fputcsv($stream, $cells, ',', '"', '', "\r\n") === false) {
            throw new RuntimeException('The CSV row could not be written.');
        }
    }

    /**
     * @param  resource  $stream
     */
    private function assertByteLimit($stream): void
    {
        $bytes = ftell($stream);

        if (! is_int($bytes)) {
            throw new RuntimeException('The CSV byte count could not be determined.');
        }

        if ($bytes > $this->limit('max_bytes')) {
            throw new InvalidArgumentException('The CSV byte limit was exceeded.');
        }
    }

    private function limit(string $name): int
    {
        $limit = $this->config->get("platform.exports.{$name}");

        if (! is_int($limit) || $limit < 1) {
            throw new RuntimeException("The CSV {$name} limit is not configured correctly.");
        }

        return $limit;
    }
}
