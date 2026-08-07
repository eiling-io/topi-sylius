<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\ApiClient\Common;

class ProductSummary
{
    public ContractTermsSummary $availableContractTerms;

    public ?string $id = null;

    public ?bool $isSupported = null;

    public ProductReference $sellerProductReference;

    /**
     * @return list<string>
     */
    public function convertToAvailableContractTermsList(): array
    {
        $availableContractTerms = [];
        if ($this->availableContractTerms->canRent) {
            $availableContractTerms[] = 'rent';
        }
        if ($this->availableContractTerms->canPayNow) {
            $availableContractTerms[] = 'pay_now';
        }

        return $availableContractTerms;
    }
}
