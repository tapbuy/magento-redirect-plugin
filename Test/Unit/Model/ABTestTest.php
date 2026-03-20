<?php

declare(strict_types=1);

namespace Tapbuy\RedirectTracking\Test\Unit\Model;

use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\RedirectTracking\Api\ConfigInterface;
use Tapbuy\RedirectTracking\Api\DataHelperInterface;
use Tapbuy\RedirectTracking\Api\LoggerInterface;
use Tapbuy\RedirectTracking\Api\TapbuyConstants;
use Tapbuy\RedirectTracking\Api\TapbuyRequestDetectorInterface;
use Tapbuy\RedirectTracking\Api\TapbuyServiceInterface;
use Tapbuy\RedirectTracking\Model\ABTest;

class ABTestTest extends TestCase
{
    private ABTest $abTest;
    private ConfigInterface&MockObject $config;
    private TapbuyServiceInterface&MockObject $service;
    private DataHelperInterface&MockObject $helper;
    private LoggerInterface&MockObject $logger;
    private TapbuyRequestDetectorInterface&MockObject $requestDetector;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigInterface::class);
        $this->service = $this->createMock(TapbuyServiceInterface::class);
        $this->helper = $this->createMock(DataHelperInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->requestDetector = $this->createMock(TapbuyRequestDetectorInterface::class);

        $this->abTest = new ABTest(
            $this->config,
            $this->service,
            $this->helper,
            $this->logger,
            $this->requestDetector
        );
    }

    public function testReturnsFalseWhenDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $order = $this->createMock(Order::class);

        $this->assertFalse($this->abTest->processOrderTransaction($order));
    }

    public function testReturnsFalseWhenTapbuyCall(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->requestDetector->method('isTapbuyCall')->willReturn(true);
        $order = $this->createMock(Order::class);

        $this->assertFalse($this->abTest->processOrderTransaction($order));
    }

    public function testReturnsFalseWhenAlreadyTracked(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->requestDetector->method('isTapbuyCall')->willReturn(false);

        $order = $this->createMock(Order::class);
        $order->method('getData')
            ->with(TapbuyConstants::ABTEST_TRACKING_FLAG)
            ->willReturn(true);

        $this->assertFalse($this->abTest->processOrderTransaction($order));
    }

    public function testReturnsTrueOnSuccessfulTransaction(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->requestDetector->method('isTapbuyCall')->willReturn(false);

        $order = $this->createMock(Order::class);
        $order->method('getData')->willReturn(null);
        $order->method('getId')->willReturn(1);
        $order->method('getIncrementId')->willReturn('100000001');

        $this->service->method('sendTransactionForOrder')
            ->willReturn(['id' => 'test-id', 'success' => true]);

        $order->expects($this->once())
            ->method('setData')
            ->with(TapbuyConstants::ABTEST_TRACKING_FLAG, true);

        $this->helper->expects($this->once())
            ->method('updateABTestCookie')
            ->with('test-id');

        $this->assertTrue($this->abTest->processOrderTransaction($order));
    }

    public function testReturnsFalseWhenServiceReturnsNoResult(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->requestDetector->method('isTapbuyCall')->willReturn(false);

        $order = $this->createMock(Order::class);
        $order->method('getData')->willReturn(null);
        $order->method('getId')->willReturn(1);
        $order->method('getIncrementId')->willReturn('100000001');

        $this->service->method('sendTransactionForOrder')->willReturn(false);

        $this->helper->expects($this->once())
            ->method('updateABTestCookie')
            ->with(null);

        $this->assertFalse($this->abTest->processOrderTransaction($order));
    }

    public function testReturnsFalseAndLogsOnException(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->requestDetector->method('isTapbuyCall')->willReturn(false);

        $order = $this->createMock(Order::class);
        $order->method('getData')->willReturn(null);
        $order->method('getId')->willReturn(1);
        $order->method('getIncrementId')->willReturn('100000001');

        $this->service->method('sendTransactionForOrder')
            ->willThrowException(new \RuntimeException('API error'));

        $this->logger->expects($this->once())->method('logException');
        $this->helper->expects($this->once())
            ->method('updateABTestCookie')
            ->with(null);

        $this->assertFalse($this->abTest->processOrderTransaction($order));
    }
}
