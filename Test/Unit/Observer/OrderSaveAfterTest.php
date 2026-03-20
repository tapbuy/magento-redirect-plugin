<?php

declare(strict_types=1);

namespace Tapbuy\RedirectTracking\Test\Unit\Observer;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\RedirectTracking\Api\ABTestInterface;
use Tapbuy\RedirectTracking\Api\ConfigInterface;
use Tapbuy\RedirectTracking\Api\LoggerInterface;
use Tapbuy\RedirectTracking\Observer\OrderSaveAfter;

class OrderSaveAfterTest extends TestCase
{
    private OrderSaveAfter $observer;
    private ConfigInterface&MockObject $config;
    private ABTestInterface&MockObject $abTest;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigInterface::class);
        $this->abTest = $this->createMock(ABTestInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->observer = new OrderSaveAfter(
            $this->config,
            $this->abTest,
            $this->logger
        );

        // Reset the static processedOrderIds between tests via reflection
        $reflection = new \ReflectionClass(OrderSaveAfter::class);
        $prop = $reflection->getProperty('processedOrderIds');
        $prop->setAccessible(true);
        $prop->setValue(null, []);
    }

    private function createObserverWithOrder(?Order $order): Observer
    {
        $event = $this->getMockBuilder(Event::class)
            ->disableOriginalConstructor()
            ->addMethods(['getOrder'])
            ->getMock();
        $event->method('getOrder')->willReturn($order);

        $observer = $this->createMock(Observer::class);
        $observer->method('getEvent')->willReturn($event);

        return $observer;
    }

    public function testSkipsWhenDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);

        $this->abTest->expects($this->never())->method('processOrderTransaction');

        $this->observer->execute($this->createObserverWithOrder(null));
    }

    public function testSkipsWhenModeIsGraphql(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getOrderConfirmationMode')
            ->willReturn(ConfigInterface::ORDER_CONFIRMATION_MODE_GRAPHQL);

        $this->abTest->expects($this->never())->method('processOrderTransaction');

        $this->observer->execute($this->createObserverWithOrder(null));
    }

    public function testSkipsWhenOrderIsNull(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getOrderConfirmationMode')
            ->willReturn(ConfigInterface::ORDER_CONFIRMATION_MODE_OBSERVER);

        $this->abTest->expects($this->never())->method('processOrderTransaction');
        $this->logger->expects($this->once())->method('warning');

        $this->observer->execute($this->createObserverWithOrder(null));
    }

    public function testSkipsNonTrackedState(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getOrderConfirmationMode')
            ->willReturn(ConfigInterface::ORDER_CONFIRMATION_MODE_OBSERVER);

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn(1);
        $order->method('getState')->willReturn(Order::STATE_CANCELED);

        $this->abTest->expects($this->never())->method('processOrderTransaction');

        $this->observer->execute($this->createObserverWithOrder($order));
    }

    public function testSkipsWhenStateDidNotChange(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getOrderConfirmationMode')
            ->willReturn(ConfigInterface::ORDER_CONFIRMATION_MODE_OBSERVER);

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn(1);
        $order->method('getState')->willReturn(Order::STATE_PROCESSING);
        $order->method('getOrigData')->with('state')->willReturn(Order::STATE_PROCESSING);

        $this->abTest->expects($this->never())->method('processOrderTransaction');

        $this->observer->execute($this->createObserverWithOrder($order));
    }

    public function testProcessesValidStateTransition(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getOrderConfirmationMode')
            ->willReturn(ConfigInterface::ORDER_CONFIRMATION_MODE_OBSERVER);

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn(1);
        $order->method('getIncrementId')->willReturn('100000001');
        $order->method('getState')->willReturn(Order::STATE_PROCESSING);
        $order->method('getOrigData')->with('state')->willReturn(Order::STATE_NEW);

        $this->abTest->expects($this->once())
            ->method('processOrderTransaction')
            ->with($order)
            ->willReturn(true);

        $this->observer->execute($this->createObserverWithOrder($order));
    }

    public function testSkipsDuplicateProcessing(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getOrderConfirmationMode')
            ->willReturn(ConfigInterface::ORDER_CONFIRMATION_MODE_OBSERVER);

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn(42);
        $order->method('getIncrementId')->willReturn('100000042');
        $order->method('getState')->willReturn(Order::STATE_PROCESSING);
        $order->method('getOrigData')->with('state')->willReturn(Order::STATE_NEW);

        $this->abTest->expects($this->once())->method('processOrderTransaction');

        // First call processes
        $this->observer->execute($this->createObserverWithOrder($order));
        // Second call skips (dedup)
        $this->observer->execute($this->createObserverWithOrder($order));
    }

    public function testCatchesAndLogsExceptions(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getOrderConfirmationMode')
            ->willReturn(ConfigInterface::ORDER_CONFIRMATION_MODE_OBSERVER);

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn(99);
        $order->method('getIncrementId')->willReturn('100000099');
        $order->method('getState')->willReturn(Order::STATE_NEW);
        $order->method('getOrigData')->with('state')->willReturn(null);

        $this->abTest->method('processOrderTransaction')
            ->willThrowException(new \RuntimeException('Service down'));

        $this->logger->expects($this->once())->method('logException');

        $this->observer->execute($this->createObserverWithOrder($order));
    }
}
