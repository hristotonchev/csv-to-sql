<?php

declare(strict_types=1);

namespace App\Service;

/**
 * TODO: Add DATE/DATETIME detection (common formats: Y-m-d, d/m/Y, m/d/Y).
 * TODO: Add BOOLEAN detection (true/false, yes/no, 0/1).
 * TODO: Allow per-column type overrides via a config file for edge cases.
 * TODO: Consider UNSIGNED INT detection to be more precise with age/count columns.
 */
class TypeInferenceService
{
    // Anything beyond this length becomes TEXT
    private const VARCHAR_MAX = 255;

    /**
     * @param array<int, array<string, string>> $rows
     * @return array<string, string>
     */
    public function inferTypes(array $headers, array $rows): array
    {
        $types = [];

        foreach ($headers as $column) {
            $values = array_column($rows, $column);
            $types[$column] = $this->inferColumnType($values);
        }

        return $types;
    }

    /**
     * @param string[] $values
     */
    private function inferColumnType(array $values): string
    {
        if (empty($values)) {
            return 'VARCHAR(255)';
        }

        $nonEmpty = array_filter($values, fn(string $v) => $v !== '');

        if (empty($nonEmpty)) {
            return 'VARCHAR(255)';
        }

        if ($this->allMatch($nonEmpty, fn($v) => $this->isInteger($v))) {
            return 'INT';
        }

        if ($this->allMatch($nonEmpty, fn($v) => $this->isDecimal($v))) {
            return 'DECIMAL(15,2)';
        }

        $maxLength = max(array_map('mb_strlen', array_values($nonEmpty)));

        if ($maxLength > self::VARCHAR_MAX) {
            return 'TEXT';
        }

        // Round up to the nearest clean boundary so we're not
        // creating VARCHAR(29) columns that break on slightly longer input.
        return sprintf('VARCHAR(%d)', $this->roundUpLength($maxLength));
    }

    /**
     * @param string[] $values
     */
    private function allMatch(array $values, callable $check): bool
    {
        foreach ($values as $value) {
            if (!$check($value)) {
                return false;
            }
        }

        return true;
    }

    private function isInteger(string $value): bool
    {
        return preg_match('/^-?\d+$/', $value) === 1;
    }

    private function isDecimal(string $value): bool
    {
        return preg_match('/^-?\d+\.\d+$/', $value) === 1;
    }

    private function roundUpLength(int $length): int
    {
        foreach ([50, 100, 150, 255] as $boundary) {
            if ($length <= $boundary) {
                return $boundary;
            }
        }

        return $length;
    }
}