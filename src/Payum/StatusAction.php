<?php

declare(strict_types=1);

namespace EilingIo\SyliusTopiPlugin\Payum;

use EilingIo\SyliusTopiPlugin\PaymentSettlement;
use EilingIo\SyliusTopiPlugin\PaymentStatus;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Sylius\Bundle\PayumBundle\Request\GetStatus;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

use function is_string;

#[Autoconfigure(public: true)]
#[AutoconfigureTag('payum.action', [
    'factory' => 'topi_payment',
    'alias' => 'payum.action.status',
])]
readonly class StatusAction implements ActionInterface
{
    /**
     * @param $request GetStatus
     */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        /** @var PaymentInterface $payment */
        $payment = $request->getModel();

        $rawStatus = $payment->getDetails()['status'] ?? null;
        if (!is_string($rawStatus)) {
            return;
        }

        $status = PaymentStatus::tryFrom($rawStatus);
        if ($status === null) {
            return;
        }

        match ($status->settlement()) {
            PaymentSettlement::PENDING => null,
            PaymentSettlement::CAPTURED => $request->markCaptured(),
            PaymentSettlement::FAILED => $request->markFailed(),
            PaymentSettlement::CANCELED => $request->markCanceled(),
        };
    }

    public function supports($request): bool
    {
        return $request instanceof GetStatus && $request->getFirstModel() instanceof PaymentInterface;
    }
}
