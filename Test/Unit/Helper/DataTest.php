<?php

declare(strict_types=1);

namespace Tapbuy\RedirectTracking\Test\Unit\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\State;
use Magento\Framework\HTTP\Header;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\RedirectTracking\Helper\Data;
use Tapbuy\RedirectTracking\Service\CookieService;
use Tapbuy\RedirectTracking\Service\EncryptionService;
use Tapbuy\RedirectTracking\Service\LocaleService;
use Tapbuy\RedirectTracking\Service\PixelService;

class DataTest extends TestCase
{
    private Data $helper;
    private CookieService&MockObject $cookieService;
    private EncryptionService&MockObject $encryptionService;
    private LocaleService&MockObject $localeService;
    private PixelService&MockObject $pixelService;
    private State&MockObject $appState;
    private Header&MockObject $httpHeader;
    private Http&MockObject $request;

    protected function setUp(): void
    {
        $context = $this->createMock(Context::class);
        $this->cookieService = $this->createMock(CookieService::class);
        $this->encryptionService = $this->createMock(EncryptionService::class);
        $this->localeService = $this->createMock(LocaleService::class);
        $this->pixelService = $this->createMock(PixelService::class);
        $this->appState = $this->createMock(State::class);
        $this->httpHeader = $this->createMock(Header::class);
        $this->request = $this->createMock(Http::class);

        $this->helper = new Data(
            $context,
            $this->cookieService,
            $this->encryptionService,
            $this->localeService,
            $this->pixelService,
            $this->appState,
            $this->httpHeader,
            $this->request
        );
    }

    public function testGetCurrentPathReturnsRequestUri(): void
    {
        $this->request->method('getRequestUri')->willReturn('/checkout/cart');
        $this->assertSame('/checkout/cart', $this->helper->getCurrentPath());
    }

    public function testGetLocaleDelegatesToLocaleService(): void
    {
        $this->localeService->method('getLocale')->willReturn('fr_FR');
        $this->assertSame('fr_FR', $this->helper->getLocale());
    }

    public function testGetUserAgentDelegatesToHttpHeader(): void
    {
        $this->httpHeader->method('getHttpUserAgent')->willReturn('Mozilla/5.0');
        $this->assertSame('Mozilla/5.0', $this->helper->getUserAgent());
    }

    public function testIsDevelopmentModeReturnsTrue(): void
    {
        $this->appState->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->assertTrue($this->helper->isDevelopmentMode());
    }

    public function testIsDevelopmentModeReturnsFalseOnException(): void
    {
        $this->appState->method('getMode')
            ->willThrowException(new \RuntimeException('State not set'));
        $this->assertFalse($this->helper->isDevelopmentMode());
    }

    public function testGetABTestIdDelegatesToCookieService(): void
    {
        $this->cookieService->method('getABTestId')->willReturn('test-123');
        $this->assertSame('test-123', $this->helper->getABTestId());
    }

    public function testUpdateABTestCookieDelegatesToCookieService(): void
    {
        $this->cookieService->expects($this->once())
            ->method('updateABTestCookie')
            ->with('new-id');

        $this->helper->updateABTestCookie('new-id');
    }

    public function testGeneratePixelUrlDelegatesToPixelService(): void
    {
        $this->pixelService->method('generatePixelUrl')
            ->with(['key' => 'val'])
            ->willReturn('https://shop.com/pixel');

        $this->assertSame('https://shop.com/pixel', $this->helper->generatePixelUrl(['key' => 'val']));
    }

    public function testGeneratePixelDataDelegatesToPixelService(): void
    {
        $expected = ['cart_id' => 'abc', 'action' => 'test'];
        $this->pixelService->method('generatePixelData')
            ->with('abc', [], 'test')
            ->willReturn($expected);

        $this->assertSame($expected, $this->helper->generatePixelData('abc', [], 'test'));
    }

    public function testGetTapbuyKeyDelegatesToEncryptionService(): void
    {
        $this->encryptionService->method('getTapbuyKey')
            ->with(null)
            ->willReturn('encrypted-key');

        $this->assertSame('encrypted-key', $this->helper->getTapbuyKey(null));
    }
}
