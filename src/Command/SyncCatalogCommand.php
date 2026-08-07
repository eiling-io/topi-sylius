<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\Command;

use EilingIo\SyliusTopiPlugin\Service\CatalogSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use function sprintf;

#[AsCommand(
    name: 'topi:catalog:sync',
    description: 'Syncs all active product variants to the Topi catalog API',
)]
class SyncCatalogCommand extends Command
{
    public function __construct(private readonly CatalogSyncService $catalogSyncService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('batch-size', null, InputOption::VALUE_OPTIONAL, 'Number of products per API request', 50);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $batchSize = (int) $input->getOption('batch-size');

        $io->title('Topi Catalog Sync');

        $progressBar = new ProgressBar($output);
        $progressBar->setFormat(' %current% synced, %message% skipped');
        $progressBar->setMessage('0');
        $progressBar->start();

        try {
            $result = $this->catalogSyncService->syncAll(
                $batchSize,
                function (int $synced, int $skipped) use ($progressBar): void {
                    $progressBar->setProgress($synced);
                    $progressBar->setMessage((string) $skipped);
                },
            );
        } catch (Throwable $e) {
            $progressBar->finish();
            $io->newLine(2);
            $io->error('Sync failed: ' . $e->getMessage());

            return Command::FAILURE;
        }

        $progressBar->finish();
        $io->newLine(2);
        $io->success(sprintf(
            'Done. Synced: %d, Skipped (no price): %d',
            $result['synced'],
            $result['skipped'],
        ));

        return Command::SUCCESS;
    }
}
