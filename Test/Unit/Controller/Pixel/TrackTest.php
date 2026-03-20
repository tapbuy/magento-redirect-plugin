<?php

declare(strict_types=1);

namespace Tapbuy\RedirectTracking\Test\Unit\Controller\Pixel;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\RedirectTracking\Api\ConfigInterface;
use Tapbuy\RedirectTracking\Api\DataHelperInterface;
use Tapbuy\RedirectTracking\Api\LoggerInterface;
use Tapbuy\RedirectTracking\Controller\Pixel\Track;
use Tapbuy\RedirectTracking\Model\Validator\PixelInputValidator;

class TrackTest extends TestCase
{
    private Track $controller;
    private RequestInterface&MockObject $request;
    private RawFactory&MockObject $rawFactory;
    private DataHelperInterface&MockObject $helper;
    private LoggerInterface&MockObject $logger;
    private ConfigInterface&MockObject $config;
    private PixelInputValidator&MockObject $pixelInputValidator;
    private Raw&MockObject $rawResponse;

    protected function setUp(): void
    {
        $this->request = $this->createMock(RequestInterface::class);
        $this->rawFactory = $this->createMock(RawFactory::class);
        $this->helper = $this->createMock(DataHelperInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->config = $this->createMock(ConfigInterface::class);
        $this->pixelInputValidator = $this->createMock(PixelInputValidator::class);

        $this->rawResponse = $this->createMock(Raw::class);
        $this->rawResponse->method('setHeader')->willReturnSelf();
        $this->rawResponse->method('setContents')->willReturnSelf();
        $this->rawFactory->method('create')->willReturn($this->rawResponse);

        $this->controller = new Track(
            $this->request,
            $this->rawFactory,
            $this->helper,
            $this->logger,
            $this->config,
            $this->pixelInputValidator
        );
    }

    public function testReturnsPixelResponseWhenDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);

        $result = $this->controller->execute();

        $this->assertSame($this->rawResponse, $result);
    }

    public function testReturnsPixelResponseWithNoData(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->request->method('getParam')->with('data')->willReturn(null);
        $this->request->method('getParams')->willReturn([]);

        $result = $this->controller->execute();
        $this->assertSame($this->rawResponse, $result);
    }

    public function testRejectsOversizedInput(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->request->method('getParam')->with('data')->willReturn(str_repeat('a', 3000));
        $this->request->method('getParams')->willReturn([]);

        $this->pixelInputValidator->method('isInputSizeValid')->willReturn(false);

        $this->logger->expects($this->once())->method('warning');

        $result = $this->controller->execute();
        $this->assertSame($this->rawResponse, $result);
    }

    public function testProcessesValidPixelData(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $encodedData = base64_encode(json_encode(['action' => 'redirect_check', 'test_id' => 'abc']));
        $this->request->method('getParam')->with('data')->willReturn($encodedData);
        $this->request->method('getParams')->willReturn(['data' => $encodedData]);

        $this->pixelInputValidator->method('isInputSizeValid')->willReturn(true);
        $this->pixelInputValidator->method('decodeAndSanitize')
            ->willReturn(['action' => 'redirect_check', 'test_id' => 'abc']);

        $this->helper->expects($this->once())->method('setABTestIdCookie')->with('abc');

        $result = $this->controller->execute();
        $this->assertSame($this->rawResponse, $result);
    }

    public function testSetsABTestCookieFromCookieParams(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->request->method('getParam')->with('data')->willReturn(null);
        $this->request->method('getParams')->willReturn([
            'cookie_tb-abtest-id' => 'test-123',
        ]);

        $this->pixelInputValidator->method('sanitizeCookieValue')
            ->with('test-123')
            ->willReturn('test-123');

        $this->helper->expects($this->once())
            ->method('setABTestIdCookie')
            ->with('test-123');

        $this->controller->execute();
    }

    public function testCatchesAndLogsRuntimeException(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->request->method('getParam')->willReturn('bad-data');
        $this->request->method('getParams')->willReturn([]);

        $this->pixelInputValidator->method('isInputSizeValid')->willReturn(true);
        $this->pixelInputValidator->method('decodeAndSanitize')
            ->willThrowException(new \RuntimeException('Parse error'));

        $this->logger->expects($this->once())->method('logException');

        $result = $this->controller->execute();
        $this->assertSame($this->rawResponse, $result);
    }
}
