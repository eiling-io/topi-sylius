<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\Service;

use Doctrine\ORM\EntityManagerInterface;
use EilingIo\SyliusTopiPlugin\ApiClient\Catalog\CatalogProduct;
use EilingIo\SyliusTopiPlugin\ApiClient\Catalog\Category;
use EilingIo\SyliusTopiPlugin\ApiClient\Client;
use EilingIo\SyliusTopiPlugin\ApiClient\Common\ProductReference;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Throwable;

use function count;
use function sprintf;

/**
 * The Sylius 1.x app this plugin was ported from resolved prices via its own B2B
 * price-list machinery and synced a single hardcoded channel. Neither concept exists
 * in stock Sylius, so this walks every enabled channel and resolves prices through
 * {@see VariantPriceResolver} instead. EAN/MPN identifiers and manufacturer are
 * likewise dropped — they were custom fields on the source app's own entities that
 * have no equivalent on Sylius core's ProductVariant/Product.
 */
class CatalogSyncService
{
    private const LOCALE = 'de_DE';
    private const BATCH_SIZE = 50;
    private const DB_PAGE_SIZE = 50;

    public function __construct(
        private readonly Client $topiClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly VariantPriceResolver $priceResolver,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{synced: int, skipped: int}
     */
    public function syncAll(int $batchSize = self::BATCH_SIZE, ?callable $progressCallback = null): array
    {
        $synced = 0;
        $skipped = 0;

        /** @var ChannelInterface $channel */
        foreach ($this->channelRepository->findEnabled() as $channel) {
            $batch = [];
            $lastId = 0;

            while (true) {
                $variants = $this->entityManager->createQueryBuilder()
                    ->select('v')
                    ->from(ProductVariantInterface::class, 'v')
                    ->join('v.product', 'p')
                    ->where('p.enabled = :enabled')
                    ->andWhere('v.enabled = :enabled')
                    ->andWhere('v.id > :lastId')
                    ->setParameter('enabled', true)
                    ->setParameter('lastId', $lastId)
                    ->orderBy('v.id', 'ASC')
                    ->setMaxResults(self::DB_PAGE_SIZE)
                    ->getQuery()
                    ->getResult();

                if (empty($variants)) {
                    break;
                }

                foreach ($variants as $variant) {
                    $lastId = $variant->getId();
                    $product = $this->convertVariant($variant, $channel);

                    if ($product === null) {
                        $skipped++;

                        continue;
                    }

                    $batch[] = $product;

                    if (count($batch) >= $batchSize) {
                        $this->sendBatch($batch);
                        $synced += count($batch);
                        $batch = [];

                        if ($progressCallback !== null) {
                            $progressCallback($synced, $skipped);
                        }
                    }
                }

                unset($variants);
                $this->entityManager->clear();
                gc_collect_cycles();
            }

            if (!empty($batch)) {
                $this->sendBatch($batch);
                $synced += count($batch);
                $batch = [];
            }
        }

        return [
            'synced' => $synced,
            'skipped' => $skipped,
        ];
    }

    private function convertVariant(ProductVariantInterface $variant, ChannelInterface $channel): ?CatalogProduct
    {
        try {
            $pricePayload = $this->priceResolver->resolve($variant, $channel);
        } catch (Throwable $e) {
            $this->logger->warning('topi catalog: skipping variant, price resolution failed', [
                'variant' => $variant->getCode(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($pricePayload === null || $pricePayload->gross <= 0) {
            return null;
        }

        $syliusProduct = $variant->getProduct();
        $syliusProduct->setCurrentLocale(self::LOCALE);

        $catalogProduct = new CatalogProduct();
        $catalogProduct->title = $syliusProduct->getName() ?? '';
        $catalogProduct->subtitle = mb_substr($syliusProduct->getDescription() ?? '', 0, 255);
        $catalogProduct->description = mb_substr($syliusProduct->getDescription() ?? '', 0, 2000);
        $catalogProduct->isActive = $syliusProduct->isEnabled() && $variant->isEnabled();
        $catalogProduct->price = $pricePayload;

        foreach ($syliusProduct->getTaxons() as $taxon) {
            $taxon->setCurrentLocale(self::LOCALE);
            $category = new Category();
            $category->id = $taxon->getCode();
            $category->name = $taxon->getName() ?? '';
            $category->parentCategoryId = $taxon->getParent()?->getCode() ?? '';
            $catalogProduct->sellerCategories[] = $category;
        }

        $productRef = new ProductReference();
        $productRef->source = 'syliusordernumbers';
        $productRef->reference = $variant->getCode();
        $catalogProduct->sellerProductReferences[] = $productRef;

        $slug = $syliusProduct->getSlug();
        if ($slug !== null) {
            try {
                $catalogProduct->shopProductDescriptionUrl = $this->urlGenerator->generate(
                    'sylius_shop_product_show',
                    [
                        'slug' => $slug,
                    ],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );
            } catch (RoutingExceptionInterface) {
                // URL generation can fail in a CLI context — not critical.
            }
        }

        return $catalogProduct;
    }

    /**
     * @param CatalogProduct[] $batch
     */
    private function sendBatch(array $batch): void
    {
        try {
            $this->topiClient->catalog()->importCatalog($batch);
        } catch (Throwable $e) {
            $this->logger->error('topi catalog import batch failed', [
                'count' => count($batch),
                'error' => $e->getMessage(),
                'response' => $e instanceof RequestException && $e->hasResponse()
                    ? (string) $e->getResponse()->getBody()
                    : null,
            ]);

            throw new RuntimeException(
                sprintf('topi catalog import batch failed (count: %d)', count($batch)),
                0,
                $e,
            );
        }
    }
}
