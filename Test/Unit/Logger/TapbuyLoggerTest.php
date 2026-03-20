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

    /**
     * Extract the context array from a Monolog record, handling both Monolog 2
     * (records are plain arrays) and Monolog 3 (records are LogRecord objects).
     *
     * @param array<mixed>|\Monolog\LogRecord $record
     * @return array<mixed>
     */
    private function getContext(mixed $record): array
    {
        if (is_object($record)) {
            // Monolog 3: LogRecord — context is a public property
            return (array) $record->context;
        }
        // Monolog 2: plain array
        return $record['context'];
    }

    public function testLogExceptionEnrichesContext(): void
    {
        $handler = new \Monolog\Handler\TestHandler();
        $this->logger->pushHandler($handler);

        $exception = new \RuntimeException('Test error', 42);
        $this->logger->logException('Something went wrong', $exception, ['extra' => 'data']);

        $this->assertTrue($handler->hasErrorRecords());
        $records = $handler->getRecords();
        $context = $this->getContext($records[0]);

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
        $context = $this->getContext($records[0]);

        $this->assertArrayHasKey('previous', $context['exception']);
        $this->assertSame('InvalidArgumentException', $context['exception']['previous']['class']);
        $this->assertSame('Root cause', $context['exception']['previous']['message']);
    }
}
