<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\TypeInferenceService;
use PHPUnit\Framework\TestCase;

class TypeInferenceServiceTest extends TestCase
{
    private TypeInferenceService $service;

    protected function setUp(): void
    {
        $this->service = new TypeInferenceService();
    }

    public function testInfersIntColumn(): void
    {
        $result = $this->infer(['Age'], [['Age' => '29'], ['Age' => '34']]);

        $this->assertSame('INT', $result['Age']);
    }

    public function testInfersDecimalColumn(): void
    {
        $result = $this->infer(['Salary'], [['Salary' => '55000.50'], ['Salary' => '62000.75']]);

        $this->assertSame('DECIMAL(15,2)', $result['Salary']);
    }

    public function testInfersMixedIntAndDecimalAsDecimal(): void
    {
        // 62000 alone looks like INT but the column has decimals elsewhere
        $result = $this->infer(['Salary'], [['Salary' => '55000.50'], ['Salary' => '62000']]);

        $this->assertSame('VARCHAR(50)', $result['Salary']);
    }

    public function testInfersVarcharWithRoundedBoundary(): void
    {
        $result = $this->infer(['Name'], [['Name' => 'Alice Smith'], ['Name' => 'Bob Johnson']]);

        $this->assertSame('VARCHAR(50)', $result['Name']);
    }

    public function testInfersTextForLongStrings(): void
    {
        $long   = str_repeat('a', 256);
        $result = $this->infer(['Bio'], [['Bio' => $long]]);

        $this->assertSame('TEXT', $result['Bio']);
    }

    public function testInfersVarchar255ForLongButUnderLimitStrings(): void
    {
        $value  = str_repeat('a', 200);
        $result = $this->infer(['Notes'], [['Notes' => $value]]);

        $this->assertSame('VARCHAR(255)', $result['Notes']);
    }

    public function testFallsBackToVarcharOnEmptyColumn(): void
    {
        $result = $this->infer(['Notes'], [['Notes' => ''], ['Notes' => '']]);

        $this->assertSame('VARCHAR(255)', $result['Notes']);
    }

    public function testSkipsEmptyValuesWhenInferring(): void
    {
        // Empty rows shouldn't poison the INT detection
        $result = $this->infer(['Age'], [['Age' => ''], ['Age' => '29']]);

        $this->assertSame('INT', $result['Age']);
    }

    public function testHandlesMultipleColumns(): void
    {
        $headers = ['Name', 'Age', 'Salary'];
        $rows    = [
            ['Name' => 'Alice', 'Age' => '29', 'Salary' => '55000.50'],
            ['Name' => 'Bob',   'Age' => '34', 'Salary' => '62000.75'],
        ];

        $result = $this->service->inferTypes($headers, $rows);

        $this->assertSame('VARCHAR(50)',    $result['Name']);
        $this->assertSame('INT',            $result['Age']);
        $this->assertSame('DECIMAL(15,2)',  $result['Salary']);
    }

    public function testNegativeIntegersAreDetectedAsInt(): void
    {
        $result = $this->infer(['Delta'], [['Delta' => '-5'], ['Delta' => '-200']]);

        $this->assertSame('INT', $result['Delta']);
    }

    public function testNegativeDecimalsAreDetectedAsDecimal(): void
    {
        $result = $this->infer(['Balance'], [['Balance' => '-500.75'], ['Balance' => '-200.00']]);

        $this->assertSame('DECIMAL(15,2)', $result['Balance']);
    }

    // -------------------------------------------------------------------------

    /**
     * @param string[] $headers
     * @param array<int, array<string, string>> $rows
     * @return array<string, string>
     */
    private function infer(array $headers, array $rows): array
    {
        return $this->service->inferTypes($headers, $rows);
    }
}