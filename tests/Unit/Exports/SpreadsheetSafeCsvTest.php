<?php

declare(strict_types=1);

namespace Tests\Unit\Exports;

use App\Data\CsvWriteResult;
use App\Exports\SpreadsheetSafeCsv;
use Illuminate\Config\Repository;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SpreadsheetSafeCsvTest extends TestCase
{
    public function test_dangerous_string_cells_are_neutralized_but_numbers_remain_numeric(): void
    {
        [$result, $rows, $raw] = $this->write(
            ['value'],
            [
                ['=SUM(1,1)'],
                [' +CMD'],
                ['-10'],
                ['@link'],
                ["\t=hidden"],
                ["\r=hidden"],
                ["\n=hidden"],
                ['＝SUM(1,1)'],
                [-10],
            ],
        );

        $this->assertSame(8, $result->neutralizedCellCount);
        $this->assertSame("\t=SUM(1,1)", $rows[1][0]);
        $this->assertSame("\t +CMD", $rows[2][0]);
        $this->assertSame("\t-10", $rows[3][0]);
        $this->assertSame("\t@link", $rows[4][0]);
        $this->assertSame('-10', $rows[9][0]);
        $this->assertStringContainsString("\"\t=SUM(1,1)\"", $raw);
    }

    public function test_commas_quotes_and_line_breaks_round_trip_without_creating_columns(): void
    {
        [$result, $rows] = $this->write(
            ['name', 'note'],
            [['Synthetic Client', "One, \"quoted\"\nline"]],
        );

        $this->assertSame(1, $result->rowCount);
        $this->assertSame(2, $result->columnCount);
        $this->assertSame(['name', 'note'], $rows[0]);
        $this->assertSame(['Synthetic Client', "One, \"quoted\"\nline"], $rows[1]);
    }

    public function test_rows_must_match_the_header_shape(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not match the header shape');

        $this->write(['one', 'two'], [['only-one']]);
    }

    public function test_duplicate_headers_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('headers must be unique');

        $this->write(['name', 'name'], []);
    }

    public function test_invalid_utf8_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('valid UTF-8');

        $this->write(['value'], [["\xB1\x31"]]);
    }

    public function test_null_bytes_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('null bytes');

        $this->write(['value'], [["synthetic\0value"]]);
    }

    public function test_unsupported_cell_types_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('scalar values or null');

        $writer = $this->writer();
        $stream = fopen('php://temp', 'w+b');

        try {
            $writer->write($stream, ['value'], [[['nested']]]);
        } finally {
            fclose($stream);
        }
    }

    public function test_configured_row_limit_is_enforced(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('row limit');

        $this->write(['value'], [['one'], ['two'], ['three']], [
            'max_rows' => 2,
        ]);
    }

    public function test_configured_cell_length_limit_is_enforced(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cell length limit');

        $this->write(['value'], [['four']], [
            'max_cell_characters' => 3,
        ]);
    }

    public function test_formula_neutralization_must_fit_the_cell_length_limit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('neutralized CSV cell length limit');

        $this->write(['v'], [['=12']], [
            'max_cell_characters' => 3,
        ]);
    }

    public function test_configured_byte_limit_is_enforced(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('byte limit');

        $this->write(['value'], [], [
            'max_bytes' => 5,
        ]);
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<string|int|float|bool|null>>  $inputRows
     * @param  array<string, int>  $overrides
     * @return array{0: CsvWriteResult, 1: list<list<string>>, 2: string}
     */
    private function write(array $headers, iterable $inputRows, array $overrides = []): array
    {
        $writer = $this->writer($overrides);
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            self::fail('Unable to open the test CSV stream.');
        }

        try {
            $result = $writer->write($stream, $headers, $inputRows);
            rewind($stream);
            $raw = stream_get_contents($stream);

            if (! is_string($raw)) {
                self::fail('Unable to read the test CSV stream.');
            }

            rewind($stream);
            $rows = [];

            while (($row = fgetcsv($stream, escape: '')) !== false) {
                $rows[] = $row;
            }

            return [$result, $rows, $raw];
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  array<string, int>  $overrides
     */
    private function writer(array $overrides = []): SpreadsheetSafeCsv
    {
        return new SpreadsheetSafeCsv(new Repository([
            'platform' => [
                'exports' => array_merge([
                    'max_rows' => 100,
                    'max_columns' => 10,
                    'max_cell_characters' => 1000,
                    'max_bytes' => 100000,
                ], $overrides),
            ],
        ]));
    }
}
