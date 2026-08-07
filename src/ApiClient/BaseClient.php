<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient;

use function is_array;

abstract class BaseClient
{
    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    protected function preProcessOptions(array $options): array
    {
        if (isset($options['lang'])) {
            if (isset($options['json']) && is_array($options['json'])) {
                $options['json']['Accept-Language'] = $options['lang'];
            }
            $options['headers'] = [
                'Accept-Language' => $options['lang'],
            ];

            unset($options['lang']);
        }

        return $options;
    }
}
