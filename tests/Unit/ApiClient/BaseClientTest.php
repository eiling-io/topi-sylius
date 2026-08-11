<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusTopiPlugin\Unit\ApiClient;

use EilingIo\SyliusTopiPlugin\ApiClient\BaseClient;
use PHPUnit\Framework\TestCase;

final class BaseClientTest extends TestCase
{
    public function testPreProcessOptionsWithoutLangLeavesOptionsUntouched(): void
    {
        $client = $this->client();

        self::assertSame(['json' => ['foo' => 'bar']], $client->preProcessOptions(['json' => ['foo' => 'bar']]));
    }

    public function testPreProcessOptionsWithLangAddsHeaderAndAcceptLanguageJsonField(): void
    {
        $client = $this->client();

        $result = $client->preProcessOptions([
            'lang' => 'de_DE',
            'json' => ['foo' => 'bar'],
        ]);

        self::assertArrayNotHasKey('lang', $result);
        self::assertSame(['Accept-Language' => 'de_DE'], $result['headers']);
        self::assertSame('de_DE', $result['json']['Accept-Language']);
    }

    public function testPreProcessOptionsWithLangButNoJsonOnlyAddsHeader(): void
    {
        $client = $this->client();

        $result = $client->preProcessOptions(['lang' => 'de_DE']);

        self::assertArrayNotHasKey('lang', $result);
        self::assertSame(['Accept-Language' => 'de_DE'], $result['headers']);
        self::assertArrayNotHasKey('json', $result);
    }

    /**
     * `preProcessOptions()` is protected — a minimal anonymous subclass that just
     * widens its visibility is simpler and less brittle than reflection here.
     */
    private function client(): BaseClient
    {
        return new class extends BaseClient {
            /**
             * @param array<string, mixed> $options
             * @return array<string, mixed>
             */
            public function preProcessOptions(array $options): array
            {
                return parent::preProcessOptions($options);
            }
        };
    }
}
