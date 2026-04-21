<?php

declare(strict_types=1);

namespace anvildev\craftkickback\helpers;

use Craft;
use yii\web\Response;

class CsvExportHelper
{
    /**
     * @param list<string> $headers
     * @param list<array<int|string, scalar|null>> $rows
     */
    public static function generate(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers, escape: '\\');

        foreach ($rows as $row) {
            fputcsv($handle, self::sanitizeRow($row), escape: '\\');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Defuse CSV formula injection (OWASP "CSV Injection"). Cells beginning
     * with =, +, -, @, tab, or CR are prefixed with an apostrophe so Excel /
     * Numbers / LibreOffice treat them as literals rather than executing
     * DDE/HYPERLINK/IMPORTDATA on open.
     *
     * @param array<int|string, scalar|null> $row
     * @return array<int|string, scalar|null>
     */
    private static function sanitizeRow(array $row): array
    {
        return array_map(static fn($v) => is_string($v) && $v !== '' && str_contains("=+-@\t\r", $v[0])
            ? "'" . $v : $v, $row);
    }

    /**
     * @param list<string> $headers
     * @param list<array<int|string, scalar|null>> $rows
     */
    public static function sendAsDownload(array $headers, array $rows, string $filename): Response
    {
        return Craft::$app->getResponse()->sendContentAsFile(
            self::generate($headers, $rows), $filename, ['mimeType' => 'text/csv']);
    }

    /**
     * @param string[] $headers
     * @param callable(int, int): array<int, array<int|string, scalar|null>> $rowCallback
     *   Receives (offset, limit) and returns rows; an empty array signals end of data.
     */
    public static function streamAsDownload(array $headers, callable $rowCallback, string $filename, int $chunkSize = 500): void
    {
        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->getHeaders()
            ->set('Content-Type', 'text/csv; charset=UTF-8')
            ->set('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->set('Cache-Control', 'no-cache');

        // Yii drives response streaming by calling this closure and iterating its
        // returned value; yield string chunks rather than writing to php://output.
        $response->stream = static function() use ($headers, $rowCallback, $chunkSize): \Generator {
            yield self::csvLine($headers);
            for ($offset = 0; ($rows = $rowCallback($offset, $chunkSize)); $offset += $chunkSize) {
                foreach ($rows as $row) {
                    yield self::csvLine(self::sanitizeRow($row));
                }
            }
        };

        $response->send();
    }

    /**
     * @param array<int|string, scalar|null> $row
     */
    private static function csvLine(array $row): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $row, escape: '\\');
        rewind($handle);
        $line = stream_get_contents($handle);
        fclose($handle);
        return $line;
    }
}
