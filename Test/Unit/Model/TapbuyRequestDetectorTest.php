<?php

declare(strict_types=1);

namespace Tapbuy\RedirectTracking\Test\Unit\Model;

use Magento\Framework\App\Request\Http;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\RedirectTracking\Api\TapbuyConstants;
use Tapbuy\RedirectTracking\Model\TapbuyRequestDetector;

class TapbuyRequestDetectorTest extends TestCase
{
    private TapbuyRequestDetector $detector;
    private Http&MockObject $request;

    protected function setUp(): void
    {
        $this->request = $this->createMock(Http::class);
        $this->detector = new TapbuyRequestDetector($this->request);
    }

    public function testIsTapbuyCallReturnsTrueWhenHeaderPresent(): void
    {
        $this->request->method('getHeader')
            ->with(TapbuyConstants::HTTP_HEADER_TAPBUY_CALL)
            ->willReturn('true');

        $this->assertTrue($this->detector->isTapbuyCall());
    }

    public function testIsTapbuyCallReturnsFalseWhenHeaderMissing(): void
    {
        $this->request->method('getHeader')
            ->with(TapbuyConstants::HTTP_HEADER_TAPBUY_CALL)
            ->willReturn(false);

        $this->assertFalse($this->detector->isTapbuyCall());
    }

    public function testIsTapbuyCallReturnsFalseWhenHeaderEmpty(): void
    {
        $this->request->method('getHeader')
            ->with(TapbuyConstants::HTTP_HEADER_TAPBUY_CALL)
            ->willReturn('');

        $this->assertFalse($this->detector->isTapbuyCall());
    }

    public function testGetRequestUri(): void
    {
        $this->request->method('getRequestUri')->willReturn('/graphql');

        $this->assertSame('/graphql', $this->detector->getRequestUri());
    }

    public function testGetTraceIdReturnsNullWhenNotTapbuyCall(): void
    {
        $this->request->method('getHeader')
            ->willReturnCallback(function (string $name) {
                return match ($name) {
                    TapbuyConstants::HTTP_HEADER_TAPBUY_CALL => false,
                    default => null,
                };
            });

        $this->assertNull($this->detector->getTraceId());
    }

    public function testGetTraceIdReturnsTraceIdString(): void
    {
        $this->request->method('getHeader')
            ->willReturnCallback(function (string $name) {
                return match ($name) {
                    TapbuyConstants::HTTP_HEADER_TAPBUY_CALL => 'true',
                    TapbuyConstants::HTTP_HEADER_TAPBUY_TRACE_ID => 'trace-abc-123',
                    default => null,
                };
            });

        $this->assertSame('trace-abc-123', $this->detector->getTraceId());
    }

    public function testGetTraceIdReturnsNullWhenTraceIdEmpty(): void
    {
        $this->request->method('getHeader')
            ->willReturnCallback(function (string $name) {
                return match ($name) {
                    TapbuyConstants::HTTP_HEADER_TAPBUY_CALL => 'true',
                    TapbuyConstants::HTTP_HEADER_TAPBUY_TRACE_ID => '',
                    default => null,
                };
            });

        $this->assertNull($this->detector->getTraceId());
    }
}
