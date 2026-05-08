<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

/**
 * TODO: Add support for different delimiters (tab, pipe, semicolon) via config option.
 * TODO: Add support for UTF-8 BOM detection and stripping for Excel-exported CSVs.
 * TODO: For 100k+ row files, consider streaming/chunked reading instead of loading all rows at once.
 */
class CsvReaderService
{
    /**
     * @return array{headers: string[], rows: array<int, array<string, string>>}
     */
    public function read(string $filePath): array
    {
        $this->validateFile($filePath);

        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            throw new RuntimeException(sprintf('Could not open file: %s', $filePath));
        }

        try {
            return $this->parse($handle);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param resource $handle
     * @return array{headers: string[], rows: array<int, array<string, string>>}
     */
    private function parse(mixed $handle): array
    {
        $headers = $this->readHeaders($handle);
        $rows    = $this->readRows($handle, $headers);

        return [
            'headers' => $headers,
            'rows'    => $rows,
        ];
    }

    /**
     * @param resource $handle
     * @return string[]
     */
    private function readHeaders(mixed $handle): array
    {
        $headers = fgetcsv($handle, escape: '');

        if ($headers === false || $headers === null) {
            throw new RuntimeException('CSV file is empty or has no header row.');
        }

        $headers = array_map('trim', $headers);

        if (count($headers) !== count(array_unique($headers))) {
            throw new RuntimeException('CSV file contains duplicate column names.');
        }

        return $headers;
    }

    /**
     * @param resource $handle
     * @param string[] $headers
     * @return array<int, array<string, string>>
     */
    private function readRows(mixed $handle, array $headers): array
    {
        $rows       = [];
        $lineNumber = 1;

        while (($data = fgetcsv($handle, escape: '')) !== false) {
            $lineNumber++;

            if ($data === [null]) {
                continue;
            }

            if (count($data) !== count($headers)) {
                throw new RuntimeException(sprintf(
                    'Row %d has %d columns, expected %d.',
                    $lineNumber,
                    count($data),
                    count($headers)
                ));
            }

            $rows[] = array_combine($headers, array_map('trim', $data));
        }

        return $rows;
    }

    private function validateFile(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException(sprintf('File not found: %s', $filePath));
        }

        if (!is_readable($filePath)) {
            throw new RuntimeException(sprintf('File is not readable: %s', $filePath));
        }

        if (pathinfo($filePath, PATHINFO_EXTENSION) !== 'csv') {
            throw new RuntimeException(sprintf('File must be a CSV: %s', $filePath));
        }
    }
}