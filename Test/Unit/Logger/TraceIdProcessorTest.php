<?php

declare(strict_types=1);

namespace Tapbuy\RedirectTracking\Test\Unit\Logger;

use Monolog\LogRecord;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\RedirectTracking\Api\TapbuyConstants;
use Tapbuy\RedirectTracking\Api\TapbuyRequestDetectorInterface;
use Tapbuy\RedirectTracking\Logger\TraceIdProcessor;

class TraceIdProcessorTest extends TestCase
{
    private TraceIdProcessor $processor;
    private TapbuyRequestDetectorInterface&MockObject $detector;

    protected function setUp(): void
    {
        $this->detector = $this->createMock(TapbuyRequestDetectorInterface::class);
        $this->processor = new TraceIdProcessor($this->detector);
    }

    public function testAddsTraceIdToArrayRecord(): void
    {
        $this->detector->method('getTraceId')->willReturn('trace-abc-123');

        $record = [
            'message' => 'test',
            'context' => [],
            'level' => 200,
            'level_name' => 'INFO',
            'channel' => 'tapbuy',
            'datetime' => new \DateTimeImmutable(),
            'extra' => [],
        ];

        $result = ($this->processor)($record);

        $this->assertSame('trace-abc-123', $result['context'][TapbuyConstants::LOG_CONTEXT_TRACE_ID]);
    }

    public function testDoesNotAddTraceIdWhenNull(): void
    {
        $this->detector->method('getTraceId')->willReturn(null);

        $record = [
            'message' => 'test',
            'context' => [],
            'level' => 200,
            'level_name' => 'INFO',
            'channel' => 'tapbuy',
            'datetime' => new \DateTimeImmutable(),
            'extra' => [],
        ];

        $result = ($this->processor)($record);

        $this->assertArrayNotHasKey(TapbuyConstants::LOG_CONTEXT_TRACE_ID, $result['context']);
    }

    public function testCachesTraceIdAcrossCalls(): void
    {
        $this->detector->expects($this->once())->method('getTraceId')->willReturn('cached-id');

        $record = [
            'message' => 'test',
            'context' => [],
            'level' => 200,
            'level_name' => 'INFO',
            'channel' => 'tapbuy',
            'datetime' => new \DateTimeImmutable(),
            'extra' => [],
        ];

        $result1 = ($this->processor)($record);
        $result2 = ($this->processor)($record);

        $this->assertSame('cached-id', $result1['context'][TapbuyConstants::LOG_CONTEXT_TRACE_ID]);
        $this->assertSame('cached-id', $result2['context'][TapbuyConstants::LOG_CONTEXT_TRACE_ID]);
    }

    public function testHandlesExceptionInDetector(): void
    {
        $this->detector->method('getTraceId')
            ->willThrowException(new \RuntimeException('No request'));

        $record = [
            'message' => 'test',
            'context' => [],
            'level' => 200,
            'level_name' => 'INFO',
            'channel' => 'tapbuy',
            'datetime' => new \DateTimeImmutable(),
            'extra' => [],
        ];

        $result = ($this->processor)($record);

        $this->assertArrayNotHasKey(TapbuyConstants::LOG_CONTEXT_TRACE_ID, $result['context']);
    }
}
