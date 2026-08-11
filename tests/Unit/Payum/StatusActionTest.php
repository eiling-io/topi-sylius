<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusTopiPlugin\Unit\Payum;

use EilingIo\SyliusTopiPlugin\Payum\StatusAction;
use Payum\Core\Exception\RequestNotSupportedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\PayumBundle\Request\GetStatus;
use Sylius\Component\Core\Model\PaymentInterface;

final class StatusActionTest extends TestCase
{
    public function testExecuteMarksCapturedForAnOrderCreatedStatus(): void
    {
        $request = $this->requestFor(['status' => 'order_created']);
        $request->expects($this->once())->method('markCaptured');
        $request->expects($this->never())->method('markFailed');
        $request->expects($this->never())->method('markCanceled');

        (new StatusAction())->execute($request);
    }

    public function testExecuteMarksFailedForADeclinedOffer(): void
    {
        $request = $this->requestFor(['status' => 'offer_declined']);
        $request->expects($this->once())->method('markFailed');

        (new StatusAction())->execute($request);
    }

    public function testExecuteMarksCanceledForAVoidedOffer(): void
    {
        $request = $this->requestFor(['status' => 'offer_voided']);
        $request->expects($this->once())->method('markCanceled');

        (new StatusAction())->execute($request);
    }

    public function testExecuteDoesNothingForAPendingStatus(): void
    {
        $request = $this->requestFor(['status' => 'offer_created']);
        $request->expects($this->never())->method('markCaptured');
        $request->expects($this->never())->method('markFailed');
        $request->expects($this->never())->method('markCanceled');

        (new StatusAction())->execute($request);
    }

    public function testExecuteDoesNothingWhenNoStatusIsStored(): void
    {
        $request = $this->requestFor([]);
        $request->expects($this->never())->method('markCaptured');

        (new StatusAction())->execute($request);
    }

    public function testExecuteDoesNothingForAnUnknownStatusValue(): void
    {
        $request = $this->requestFor(['status' => 'not_a_real_status']);
        $request->expects($this->never())->method('markCaptured');

        (new StatusAction())->execute($request);
    }

    public function testExecuteRejectsAnUnsupportedRequest(): void
    {
        $this->expectException(RequestNotSupportedException::class);

        (new StatusAction())->execute(new \stdClass());
    }

    public function testSupportsAGetStatusRequestForAPayment(): void
    {
        $request = $this->requestFor([]);

        self::assertTrue((new StatusAction())->supports($request));
    }

    public function testSupportsRejectsAnythingElse(): void
    {
        self::assertFalse((new StatusAction())->supports(new \stdClass()));
    }

    /**
     * @param array<string, mixed> $details
     * @return GetStatus&MockObject
     */
    private function requestFor(array $details): GetStatus
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getDetails')->willReturn($details);

        $request = $this->createMock(GetStatus::class);
        // execute() reads getModel(); supports() (called internally via
        // RequestNotSupportedException::assertSupports()) reads getFirstModel() —
        // both need to resolve to the same payment mock for either to work.
        $request->method('getModel')->willReturn($payment);
        $request->method('getFirstModel')->willReturn($payment);

        return $request;
    }
}
