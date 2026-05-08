# CSV to SQL

A Symfony Console application that reads a CSV file and outputs a `CREATE TABLE` SQL statement by inferring column types from the data. Includes an ElasticSearch indexer for full-text and fuzzy search across CSV data.

## Requirements

- PHP 8.2+
- Composer
- Docker (optional, for ElasticSearch)

## Setup

```bash
git clone git@github.com:hristotonchev/csv-to-sql.git
cd csv-to-sql
composer install
```

## Commands

### csv:to-sql

Reads a CSV file and outputs a SQL `CREATE TABLE` statement.

```bash
php bin/console csv:to-sql <file> [--table=TABLE_NAME]
```

```bash
# Use the bundled sample
php bin/console csv:to-sql fixtures/sample.csv

# Custom table name
php bin/console csv:to-sql fixtures/sample.csv --table=employees

# Verbose — shows row and column count summary
php bin/console csv:to-sql fixtures/sample.csv -v
```

**Example output:**

```sql
CREATE TABLE `sample` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `Name` VARCHAR(50) NOT NULL,
    `Age` INT NOT NULL,
    `Grade` VARCHAR(50) NOT NULL,
    `Salary` DECIMAL(15,2) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### csv:index-es

Indexes a CSV file into an ElasticSearch index.

```bash
php bin/console csv:index-es <file> [--index=INDEX_NAME] [--mapping=MAPPING_FILE]
```

```bash
# Index using default mapping
php bin/console csv:index-es fixtures/sample.csv --index=employees

# Custom mapping file (resolved from config/elasticsearch/)
php bin/console csv:index-es fixtures/sample.csv --index=employees --mapping=employees_mapping.json

# Custom ElasticSearch host
ES_URL=http://localhost:9201 php bin/console csv:index-es fixtures/sample.csv --index=employees
```

## Type Inference Rules

The application scans all values in a column and applies the following rules in order:

| Pattern | SQL type |
|---|---|
| All integers | `INT` |
| All decimals | `DECIMAL(15,2)` |
| String ≤ 50 chars | `VARCHAR(50)` |
| String ≤ 100 chars | `VARCHAR(100)` |
| String ≤ 150 chars | `VARCHAR(150)` |
| String ≤ 255 chars | `VARCHAR(255)` |
| String > 255 chars | `TEXT` |
| Empty column | `VARCHAR(255)` |

Empty values within a column are skipped during inference so that one blank cell does not force the entire column to `VARCHAR`.

## ElasticSearch

### Starting ElasticSearch locally

```bash
docker run -d \
  --name es-local \
  -p 9200:9200 \
  -e "discovery.type=single-node" \
  -e "xpack.security.enabled=false" \
  elasticsearch:8.13.0
```

Verify it is running:

```bash
curl http://localhost:9200
```

### Index mapping

The mapping file lives at `config/elasticsearch/employees_mapping.json` and defines:

- `name` — `text` with a custom `name_analyzer` (lowercase + ASCII folding) for full-text search, plus a `keyword` sub-field for exact matching and sorting
- `age` — `integer`
- `grade` — `keyword` (exact match, used for filtering)
- `salary` — `scaled_float` with a scaling factor of 100
- `indexed_at` — `date` in `strict_date_time` format
- `dynamic: strict` — ES rejects any fields not declared in the mapping

### Searching

Fuzzy search (handles typos):

```bash
curl -X GET "http://localhost:9200/employees/_search" \
  -H "Content-Type: application/json" \
  -d '{
    "query": {
      "multi_match": {
        "query": "Alise",
        "fields": ["name^2", "grade"],
        "fuzziness": "AUTO"
      }
    }
  }'
```

Filter by grade, sorted by salary descending:

```bash
curl -X GET "http://localhost:9200/employees/_search" \
  -H "Content-Type: application/json" \
  -d '{
    "query": {
      "term": { "grade": "L4" }
    },
    "sort": [{ "salary": { "order": "desc" } }]
  }'
```

### Keeping SQL and ElasticSearch in sync

When a user updates an employee record via a web interface, both the SQL database and the ElasticSearch index need to reflect the change. The recommended approach is the **outbox pattern** using Symfony Messenger. When the update is saved to the database, an `EmployeeUpdatedEvent` is dispatched and persisted to an outbox table within the same database transaction, guaranteeing the event is never lost. A Messenger consumer then picks up the event asynchronously and updates the ES document via the `_doc` API. This decouples the write path from the indexing path so a slow or unavailable ES instance never blocks the user. For high-throughput scenarios the consumer can batch updates using the `_bulk` API. A dead-letter queue handles failed messages so no update is silently dropped, and a full reindex command (`csv:index-es`) can be used to rebuild the index from scratch if the two stores drift out of sync.

## Running Tests

```bash
# Full suite
composer test

# Single file
vendor/bin/phpunit tests/Unit/CsvReaderServiceTest.php
```

## Static Analysis

```bash
composer phpstan
```

## Linting

```bash
composer lint
```

## CI/CD

GitHub Actions runs on every push and pull request to `main` across PHP 8.2, 8.3, and 8.4 in parallel:

1. Validate `composer.json`
2. Install dependencies
3. PHP syntax lint
4. PHPStan static analysis (level 8)
5. PHPUnit test suite

## Project Structure

```
csv-to-sql/
├── .github/
│   └── workflows/
│       └── ci.yml
├── config/
│   └── elasticsearch/
│       └── employees_mapping.json
├── fixtures/
│   └── sample.csv
├── src/
│   ├── Command/
│   │   ├── CsvToSqlCommand.php
│   │   └── CsvIndexEsCommand.php
│   ├── ElasticSearch/
│   │   ├── CsvIndexerService.php
│   │   ├── HttpClientInterface.php
│   │   ├── IndexMappingBuilder.php
│   │   └── NativeHttpClient.php
│   └── Service/
│       ├── CsvReaderService.php
│       ├── SqlGeneratorService.php
│       └── TypeInferenceService.php
├── tests/
│   └── Unit/
│       ├── ElasticSearch/
│       │   ├── CsvIndexerServiceTest.php
│       │   └── IndexMappingBuilderTest.php
│       ├── CsvReaderServiceTest.php
│       ├── SqlGeneratorServiceTest.php
│       └── TypeInferenceServiceTest.php
├── bin/
│   └── console
├── composer.json
├── phpstan.neon
└── phpunit.xml
```

## Potential Improvements

- **Delimiter detection** — auto-detect tab, pipe, and semicolon separated files
- **Date/boolean inference** — extend `TypeInferenceService` to detect `DATE`, `DATETIME`, and `BOOLEAN` columns
- **Multiple SQL dialects** — support PostgreSQL and SQLite output via a strategy pattern
- **Bulk ES indexing** — switch to the `_bulk` API for files with 100k+ rows
- **NOT NULL inference** — mark columns as nullable when empty values are present in the data
- **Index aliases** — zero-downtime reindexing by swapping ES aliases instead of deleting and recreating
- **Output to file** — add a `--output` option to write the SQL to a file instead of stdout
- **Streaming large files** — process CSV files in chunks instead of loading all rows into memory
