<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Helpers;

use anvildev\craftkickback\helpers\CsvExportHelper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CsvExportHelperTest extends TestCase
{
    #[Test]
    public function generateOutputsHeaders(): void
    {
        $csv = CsvExportHelper::generate(['Name', 'Email'], []);
        $this->assertStringContainsString('Name', $csv);
        $this->assertStringContainsString('Email', $csv);
    }

    #[Test]
    public function generateWithNoRowsReturnsOnlyHeaderLine(): void
    {
        $csv = CsvExportHelper::generate(['A', 'B'], []);
        $lines = array_filter(explode("\n", trim($csv)));
        $this->assertCount(1, $lines);
    }

    #[Test]
    public function generateIncludesRowData(): void
    {
        $csv = CsvExportHelper::generate(['Name'], [['Alice'], ['Bob']]);
        $this->assertStringContainsString('Alice', $csv);
        $this->assertStringContainsString('Bob', $csv);
    }

    #[Test]
    public function generateOutputsCorrectNumberOfLines(): void
    {
        $csv = CsvExportHelper::generate(['Col'], [['r1'], ['r2'], ['r3']]);
        $lines = array_filter(explode("\n", trim($csv)));
        $this->assertCount(4, $lines); // 1 header + 3 rows
    }

    #[Test]
    public function generateEscapesCommasInValues(): void
    {
        $csv = CsvExportHelper::generate(['Name'], [['Doe, Jane']]);
        // fputcsv wraps fields containing commas in double quotes
        $this->assertStringContainsString('"Doe, Jane"', $csv);
    }

    #[Test]
    public function generateEscapesQuotesInValues(): void
    {
        $csv = CsvExportHelper::generate(['Quote'], [['She said "hello"']]);
        // fputcsv escapes embedded quotes by doubling them
        $this->assertStringContainsString('""hello""', $csv);
    }

    #[Test]
    public function generateHandlesNewlinesInValues(): void
    {
        $csv = CsvExportHelper::generate(['Text'], [["line1\nline2"]]);
        // fputcsv wraps fields with newlines in double quotes
        $this->assertStringContainsString("\"line1\nline2\"", $csv);
    }

    #[Test]
    public function generateReturnsString(): void
    {
        $result = CsvExportHelper::generate([], []);
        $this->assertIsString($result);
    }
}
