<?php

declare(strict_types=1);

namespace App\Service;

/**
 * TODO: Add support for composite primary keys.
 * TODO: Add optional NOT NULL constraints based on whether any value in the column was empty.
 * TODO: Add INDEX generation for columns likely used in WHERE clauses (e.g. detected foreign key names ending in _id).
 * TODO: Support multiple SQL dialects (PostgreSQL, SQLite) via a strategy pattern.
 */
class SqlGeneratorService
{
    /**
     * @param array<string, string> $columnTypes
     */
    public function generate(string $tableName, array $columnTypes): string
    {
        $this->validateTableName($tableName);

        $columns   = $this->buildColumnDefinitions($columnTypes);
        $columns[] = $this->primaryKeyDefinition();

        return $this->formatStatement($tableName, $columns);
    }

    /**
     * @param array<string, string> $columnTypes
     * @return string[]
     */
    private function buildColumnDefinitions(array $columnTypes): array
    {
        $definitions = [];

        foreach ($columnTypes as $column => $type) {
            $definitions[] = sprintf(
                '    `%s` %s NOT NULL',
                $this->sanitizeIdentifier($column),
                $type
            );
        }

        return $definitions;
    }

    private function primaryKeyDefinition(): string
    {
        return '    PRIMARY KEY (`id`)';
    }

    /**
     * @param string[] $columns
     */
    private function formatStatement(string $tableName, array $columns): string
    {
        return sprintf(
            "CREATE TABLE `%s` (\n    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,\n%s\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            $tableName,
            implode(",\n", $columns)
        );
    }

    private function validateTableName(string $tableName): void
    {
        if (trim($tableName) === '') {
            throw new \InvalidArgumentException('Table name cannot be empty.');
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $tableName)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid table name "%s". Only letters, numbers and underscores are allowed.',
                $tableName
            ));
        }
    }

    // Strips backticks to prevent identifier injection
    private function sanitizeIdentifier(string $name): string
    {
        return str_replace('`', '', $name);
    }
}