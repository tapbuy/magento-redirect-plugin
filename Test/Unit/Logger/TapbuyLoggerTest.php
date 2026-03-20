<?php

declare(strict_types=1);

namespace Tapbuy\RedirectTracking\Test\Unit\Logger;

use PHPUnit\Framework\TestCase;
use Tapbuy\RedirectTracking\Logger\TapbuyLogger;

class TapbuyLoggerTest extends TestCase
{
    private TapbuyLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new TapbuyLogger('tapbuy_test');
    }

    public function testLogExceptionEnrichesContext(): void
    {
        $handler = new \Monolog\Handler\TestHandler();
        $this->logger->pushHandler($handler);

        $exception = new \RuntimeException('Test error', 42);
        $this->logger->logException('Something went wrong', $exception, ['extra' => 'data']);

        $this->assertTrue($handler->hasErrorRecords());
        $records = $handler->getRecords();
        $context = $records[0]['context'];

        $this->assertSame('RuntimeException', $context['exception']['class']);
        $this->assertSame('Test error', $context['exception']['message']);
        $this->assertSame(42, $context['exception']['code']);
        $this->assertSame('data', $context['extra']);
        $this->assertArrayHasKey('stacktrace', $context['exception']);
    }

    public function testLogExceptionIncludesPreviousException(): void
    {
        $handler = new \Monolog\Handler\TestHandler();
        $this->logger->pushHandler($handler);

        $previous = new \InvalidArgumentException('Root cause');
        $exception = new \RuntimeException('Wrapper', 0, $previous);

        $this->logger->logException('Nested error', $exception);

        $records = $handler->getRecords();
        $context = $records[0]['context'];

        $this->assertArrayHasKey('previous', $context['exception']);
        $this->assertSame('InvalidArgumentException', $context['exception']['previous']['class']);
        $this->assertSame('Root cause', $context['exception']['previous']['message']);
    }
}
