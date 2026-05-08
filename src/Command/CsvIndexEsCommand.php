<?php

declare(strict_types=1);

namespace App\Command;

use App\ElasticSearch\CsvIndexerService;
use App\ElasticSearch\IndexMappingBuilder;
use App\Service\CsvReaderService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'csv:index-es',
    description: 'Indexes a CSV file into an ElasticSearch index.',
)]
class CsvIndexEsCommand extends Command
{
    public function __construct(
        private readonly CsvReaderService    $csvReader,
        private readonly CsvIndexerService   $indexer,
        private readonly IndexMappingBuilder $mappingBuilder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the CSV file.')
            ->addOption('index', 'i', InputOption::VALUE_OPTIONAL, 'ElasticSearch index name. Defaults to CSV filename.')
            ->addOption('mapping', 'm', InputOption::VALUE_OPTIONAL, 'Mapping filename from config/elasticsearch/.', 'employees_mapping.json')
            // TODO: Add --batch-size option once bulk indexing is implemented.
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $filePath */
        $filePath  = $input->getArgument('file');

        /** @var string|null $indexOption */
        $indexOption = $input->getOption('index');
        $indexName   = $indexOption ?? pathinfo($filePath, PATHINFO_FILENAME);

        /** @var string $mappingFile */
        $mappingFile = $input->getOption('mapping');

        try {
            $mapping = $this->mappingBuilder->fromFile($mappingFile);

            $csv = $this->csvReader->read($filePath);

            $io->text(sprintf('Creating index <info>%s</info>...', $indexName));
            $this->indexer->createIndex($indexName, $mapping);

            $io->text(sprintf('Indexing %d rows...', count($csv['rows'])));
            $this->indexer->indexRows($indexName, $csv['rows']);

            $io->success(sprintf('Indexed %d documents into "%s".', count($csv['rows']), $indexName));
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}