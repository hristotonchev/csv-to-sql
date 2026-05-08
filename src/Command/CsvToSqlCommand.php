<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\CsvReaderService;
use App\Service\SqlGeneratorService;
use App\Service\TypeInferenceService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'csv:to-sql',
    description: 'Reads a CSV file and outputs a SQL CREATE TABLE statement.',
)]
class CsvToSqlCommand extends Command
{
    public function __construct(
        private readonly CsvReaderService     $csvReader,
        private readonly TypeInferenceService $typeInference,
        private readonly SqlGeneratorService  $sqlGenerator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'file',
                InputArgument::REQUIRED,
                'Path to the CSV file.'
            )
            ->addOption(
                'table',
                't',
                InputOption::VALUE_OPTIONAL,
                'Table name. Defaults to the CSV filename without extension.'
            )
            // TODO: Add --output option to write SQL to a file instead of stdout.
            // TODO: Add --dialect option (mysql, pgsql, sqlite) once SqlGeneratorService supports it.
            // TODO: Add --no-primary-key flag for tables that will be merged with existing schemas.
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $filePath */
        $filePath  = $input->getArgument('file');
        $tableName = $this->resolveTableName($input, $filePath);

        try {
            $csv   = $this->csvReader->read($filePath);
            $types = $this->typeInference->inferTypes($csv['headers'], $csv['rows']);
            $sql   = $this->sqlGenerator->generate($tableName, $types);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->text($sql);

        if ($output->isVerbose()) {
            $io->newLine();
            $io->success(sprintf(
                'Processed %d rows, %d columns → table `%s`',
                count($csv['rows']),
                count($csv['headers']),
                $tableName
            ));
        }

        return Command::SUCCESS;
    }

    private function resolveTableName(InputInterface $input, string $filePath): string
    {
        /** @var string|null $option */
        $option = $input->getOption('table');

        if ($option !== null && trim($option) !== '') {
            return $option;
        }

        return pathinfo($filePath, PATHINFO_FILENAME);
    }
}