<?php

declare(strict_types=1);

namespace Tapbuy\RedirectTracking\Test\Unit\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\RedirectTracking\Api\ABTestInterface;
use Tapbuy\RedirectTracking\Api\ConfigInterface;
use Tapbuy\RedirectTracking\Api\LoggerInterface;
use Tapbuy\RedirectTracking\Api\Order\OrderLocatorInterface;
use Tapbuy\RedirectTracking\Model\Resolver\ConfirmOrder;

class ConfirmOrderTest extends TestCase
{
    private ConfirmOrder $resolver;
    private OrderLocatorInterface&MockObject $orderLocator;
    private ABTestInterface&MockObject $abTest;
    private ConfigInterface&MockObject $config;
    private LoggerInterface&MockObject $logger;
    private OrderPaymentRepositoryInterface&MockObject $paymentRepository;
    private Field&MockObject $field;
    private ContextInterface&MockObject $context;
    private ResolveInfo&MockObject $info;

    protected function setUp(): void
    {
        $this->orderLocator = $this->createMock(OrderLocatorInterface::class);
        $this->abTest = $this->createMock(ABTestInterface::class);
        $this->config = $this->createMock(ConfigInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->paymentRepository = $this->createMock(OrderPaymentRepositoryInterface::class);

        $this->resolver = new ConfirmOrder(
            $this->orderLocator,
            $this->abTest,
            $this->config,
            $this->logger,
            $this->paymentRepository
        );

        $this->field = $this->createMock(Field::class);
        $this->context = $this->createMock(ContextInterface::class);
        $this->info = $this->createMock(ResolveInfo::class);
    }

    public function testReturnsTrueWhenDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);

        $result = $this->resolver->resolve($this->field, $this->context, $this->info);
        $this->assertTrue($result);
    }

    public function testThrowsWhenMissingRequiredInput(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $this->expectException(GraphQlInputException::class);

        $this->resolver->resolve($this->field, $this->context, $this->info, null, [
            'input' => ['order_number' => '100001'],
        ]);
    }

    public function testReturnsTrueWhenOrderNotFound(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $this->orderLocator->method('getByIdentifier')
            ->willThrowException(new \Magento\Framework\Exception\NoSuchEntityException(__('Not found')));

        $result = $this->resolver->resolve($this->field, $this->context, $this->info, null, [
            'input' => ['order_number' => '999999', 'ab_test_id' => 'test-id'],
        ]);

        $this->assertTrue($result);
    }

    public function testSkipsAlreadyTrackedOrder(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->willReturn(true);

        $order = $this->createMock(Order::class);
        $order->method('getPayment')->willReturn($payment);

        $this->orderLocator->method('getByIdentifier')->willReturn($order);

        $this->abTest->expects($this->never())->method('processOrderTransaction');

        $result = $this->resolver->resolve($this->field, $this->context, $this->info, null, [
            'input' => ['order_number' => '100001', 'ab_test_id' => 'test-id'],
        ]);

        $this->assertTrue($result);
    }

    public function testTracksOrderSuccessfully(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->willReturn(null);

        $order = $this->createMock(Order::class);
        $order->method('getPayment')->willReturn($payment);

        $this->orderLocator->method('getByIdentifier')->willReturn($order);

        $this->abTest->expects($this->once())
            ->method('processOrderTransaction')
            ->with($order, 'ab-test-123')
            ->willReturn(true);

        $payment->expects($this->once())
            ->method('setAdditionalInformation');

        $this->paymentRepository->expects($this->once())->method('save');

        $result = $this->resolver->resolve($this->field, $this->context, $this->info, null, [
            'input' => ['order_number' => '100001', 'ab_test_id' => 'ab-test-123'],
        ]);

        $this->assertTrue($result);
    }

    public function testReturnsTrueOnRuntimeException(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->willReturn(null);

        $order = $this->createMock(Order::class);
        $order->method('getPayment')->willReturn($payment);

        $this->orderLocator->method('getByIdentifier')->willReturn($order);
        $this->abTest->method('processOrderTransaction')
            ->willThrowException(new \RuntimeException('Service down'));

        $this->logger->expects($this->once())->method('logException');

        $result = $this->resolver->resolve($this->field, $this->context, $this->info, null, [
            'input' => ['order_number' => '100001', 'ab_test_id' => 'test-id'],
        ]);

        $this->assertTrue($result);
    }
}
