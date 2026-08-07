<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\Command;

use EilingIo\SyliusTopiPlugin\Service\ShippingMethodSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use function sprintf;

#[AsCommand(
    name: 'topi:shipping-methods:sync',
    description: 'Syncs all enabled Sylius shipping methods to the Topi API',
)]
class SyncShippingMethodsCommand extends Command
{
    public function __construct(private readonly ShippingMethodSyncService $shippingMethodSyncService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Topi Shipping Methods Sync');

        try {
            $result = $this->shippingMethodSyncService->syncAll(
                function (int $synced, int $skipped) use ($io): void {
                    $io->writeln(sprintf('  Synced: %d, Skipped: %d', $synced, $skipped));
                },
            );
        } catch (Throwable $e) {
            $io->error('Sync failed: ' . $e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Done. Synced: %d, Skipped (disabled/no name): %d',
            $result['synced'],
            $result['skipped'],
        ));

        return Command::SUCCESS;
    }
}
