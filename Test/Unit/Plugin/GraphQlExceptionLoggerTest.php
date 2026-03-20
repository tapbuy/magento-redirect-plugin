<?php

declare(strict_types=1);

namespace Tapbuy\RedirectTracking\Test\Unit\Plugin;

use GraphQL\Error\Error;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\GraphQl\Query\ErrorHandlerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\RedirectTracking\Api\LoggerInterface;
use Tapbuy\RedirectTracking\Api\TapbuyRequestDetectorInterface;
use Tapbuy\RedirectTracking\Plugin\GraphQlExceptionLogger;

class GraphQlExceptionLoggerTest extends TestCase
{
    private GraphQlExceptionLogger $plugin;
    private TapbuyRequestDetectorInterface&MockObject $detector;
    private LoggerInterface&MockObject $logger;
    private FileDriver&MockObject $fileDriver;

    protected function setUp(): void
    {
        $this->detector = $this->createMock(TapbuyRequestDetectorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->fileDriver = $this->createMock(FileDriver::class);

        $this->plugin = new GraphQlExceptionLogger(
            $this->detector,
            $this->logger,
            $this->fileDriver
        );
    }

    public function testSkipsLoggingForNonTapbuyCalls(): void
    {
        $this->detector->method('isTapbuyCall')->willReturn(false);

        $subject = $this->createMock(ErrorHandlerInterface::class);
        $formatter = function () {};

        $this->logger->expects($this->never())->method('error');

        $result = $this->plugin->beforeHandle($subject, [], $formatter);
        $this->assertSame([[], $formatter], $result);
    }

    public function testLogsGraphQlErrorForTapbuyCalls(): void
    {
        $this->detector->method('isTapbuyCall')->willReturn(true);
        $this->detector->method('getRequestUri')->willReturn('/graphql');

        $error = new Error('Something failed');
        $subject = $this->createMock(ErrorHandlerInterface::class);
        $formatter = function () {};

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Something failed', $this->isType('array'));

        $this->plugin->beforeHandle($subject, [$error], $formatter);
    }

    public function testLogsErrorWithPreviousException(): void
    {
        $this->detector->method('isTapbuyCall')->willReturn(true);
        $this->detector->method('getRequestUri')->willReturn('/graphql');

        $previous = new \RuntimeException('Root cause', 42);
        $error = new Error('GraphQL error', null, null, [], null, $previous);

        $subject = $this->createMock(ErrorHandlerInterface::class);
        $formatter = function () {};

        $this->fileDriver->method('isExists')->willReturn(false);

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'GraphQL error',
                $this->callback(function (array $context) {
                    return isset($context['exception']['class'])
                        && $context['exception']['class'] === 'RuntimeException'
                        && $context['exception']['message'] === 'Root cause';
                })
            );

        $this->plugin->beforeHandle($subject, [$error], $formatter);
    }

    public function testHandlesLoggingFailureGracefully(): void
    {
        $this->detector->method('isTapbuyCall')->willReturn(true);
        $this->detector->method('getRequestUri')
            ->willThrowException(new \RuntimeException('Request unavailable'));

        $error = new Error('Test error');
        $subject = $this->createMock(ErrorHandlerInterface::class);
        $formatter = function () {};

        // Should not throw even if logging fails
        $result = $this->plugin->beforeHandle($subject, [$error], $formatter);
        $this->assertIsArray($result);
    }
}
