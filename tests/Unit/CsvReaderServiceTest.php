<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\CsvReaderService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CsvReaderServiceTest extends TestCase
{
    private CsvReaderService $service;
    private string $tempDir;

    /** @var string[] */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->service = new CsvReaderService();
        $this->tempDir = sys_get_temp_dir();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        $this->tempFiles = [];
    }

    public function testReadsHeadersAndRowsCorrectly(): void
    {
        $file = $this->createCsvFile(
            "Name,Age,Salary\n" .
            "Alice,29,55000.50\n" .
            "Bob,34,62000\n"
        );

        $result = $this->service->read($file);

        $this->assertSame(['Name', 'Age', 'Salary'], $result['headers']);
        $this->assertCount(2, $result['rows']);
        $this->assertSame(['Name' => 'Alice', 'Age' => '29', 'Salary' => '55000.50'], $result['rows'][0]);
        $this->assertSame(['Name' => 'Bob',   'Age' => '34', 'Salary' => '62000'],    $result['rows'][1]);
    }

    public function testTrimsWhitespaceFromHeadersAndValues(): void
    {
        $file = $this->createCsvFile("Name , Age \n Alice , 29 \n");

        $result = $this->service->read($file);

        $this->assertSame(['Name', 'Age'], $result['headers']);
        $this->assertSame('Alice', $result['rows'][0]['Name']);
        $this->assertSame('29',    $result['rows'][0]['Age']);
    }

    public function testSkipsEmptyLines(): void
    {
        $file = $this->createCsvFile("Name,Age\nAlice,29\n\nBob,34\n");

        $result = $this->service->read($file);

        $this->assertCount(2, $result['rows']);
    }

    public function testThrowsOnFileNotFound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/File not found/');

        $this->service->read('/non/existent/file.csv');
    }

    public function testThrowsOnNonCsvExtension(): void
    {
        $file = $this->createTempFile('txt', "Name,Age\nAlice,29\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/must be a CSV/');

        $this->service->read($file);
    }

    public function testThrowsOnEmptyFile(): void
    {
        $file = $this->createCsvFile('');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/empty or has no header row/');

        $this->service->read($file);
    }

    public function testThrowsOnDuplicateColumnNames(): void
    {
        $file = $this->createCsvFile("Name,Age,Name\nAlice,29,Smith\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/duplicate column names/');

        $this->service->read($file);
    }

    public function testThrowsOnRowColumnCountMismatch(): void
    {
        $file = $this->createCsvFile("Name,Age\nAlice,29,ExtraColumn\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Row 2 has 3 columns, expected 2/');

        $this->service->read($file);
    }

    public function testHandlesHeaderOnlyFile(): void
    {
        $file = $this->createCsvFile("Name,Age,Salary\n");

        $result = $this->service->read($file);

        $this->assertSame(['Name', 'Age', 'Salary'], $result['headers']);
        $this->assertCount(0, $result['rows']);
    }

    // -------------------------------------------------------------------------

    private function createCsvFile(string $content): string
    {
        return $this->createTempFile('csv', $content);
    }

    private function createTempFile(string $extension, string $content): string
    {
        $path = tempnam($this->tempDir, 'csv_test_');

        if ($path === false) {
            throw new \RuntimeException('Could not create temp file.');
        }

        $finalPath = $path . '.' . $extension;
        rename($path, $finalPath);
        file_put_contents($finalPath, $content);

        $this->tempFiles[] = $finalPath;

        return $finalPath;
    }
}