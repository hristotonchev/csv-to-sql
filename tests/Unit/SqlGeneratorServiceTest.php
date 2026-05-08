<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\SqlGeneratorService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SqlGeneratorServiceTest extends TestCase
{
    private SqlGeneratorService $service;

    protected function setUp(): void
    {
        $this->service = new SqlGeneratorService();
    }

    public function testGeneratesValidCreateTableStatement(): void
    {
        $sql = $this->service->generate('employees', [
            'Name'   => 'VARCHAR(50)',
            'Age'    => 'INT',
            'Salary' => 'DECIMAL(15,2)',
        ]);

        $this->assertStringContainsString('CREATE TABLE `employees`', $sql);
        $this->assertStringContainsString('`Name` VARCHAR(50) NOT NULL', $sql);
        $this->assertStringContainsString('`Age` INT NOT NULL', $sql);
        $this->assertStringContainsString('`Salary` DECIMAL(15,2) NOT NULL', $sql);
    }

    public function testAlwaysIncludesAutoIncrementPrimaryKey(): void
    {
        $sql = $this->service->generate('users', ['Name' => 'VARCHAR(50)']);

        $this->assertStringContainsString('`id` INT UNSIGNED NOT NULL AUTO_INCREMENT', $sql);
        $this->assertStringContainsString('PRIMARY KEY (`id`)', $sql);
    }

    public function testIncludesInnoDbAndCharsetDefaults(): void
    {
        $sql = $this->service->generate('users', ['Name' => 'VARCHAR(50)']);

        $this->assertStringContainsString('ENGINE=InnoDB', $sql);
        $this->assertStringContainsString('DEFAULT CHARSET=utf8mb4', $sql);
        $this->assertStringContainsString('COLLATE=utf8mb4_unicode_ci', $sql);
    }

    public function testThrowsOnEmptyTableName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/cannot be empty/');

        $this->service->generate('', ['Name' => 'VARCHAR(50)']);
    }

    public function testThrowsOnInvalidTableName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid table name/');

        $this->service->generate('123invalid', ['Name' => 'VARCHAR(50)']);
    }

    public function testThrowsOnTableNameWithSpecialChars(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->generate('my-table', ['Name' => 'VARCHAR(50)']);
    }

    public function testStripsBackticksFromColumnNames(): void
    {
        $sql = $this->service->generate('test', ['col`name' => 'VARCHAR(50)']);

        $this->assertStringNotContainsString('``', $sql);
        $this->assertStringContainsString('`colname`', $sql);
    }

    public function testStatementEndsWithSemicolon(): void
    {
        $sql = $this->service->generate('users', ['Name' => 'VARCHAR(50)']);

        $this->assertStringEndsWith(';', $sql);
    }

    public function testFullOutputMatchesExpectedStructure(): void
    {
        $sql = $this->service->generate('employees', [
            'Name'   => 'VARCHAR(50)',
            'Age'    => 'INT',
            'Salary' => 'DECIMAL(15,2)',
        ]);

        $expected = <<<SQL
        CREATE TABLE `employees` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `Name` VARCHAR(50) NOT NULL,
            `Age` INT NOT NULL,
            `Salary` DECIMAL(15,2) NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;

        $this->assertSame($expected, $sql);
    }
}