<?php

declare(strict_types=1);

namespace Tapbuy\RedirectTracking\Test\Unit\Service;

use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\RedirectTracking\Service\PixelService;

class PixelServiceTest extends TestCase
{
    private PixelService $pixelService;
    private StoreManagerInterface&MockObject $storeManager;

    protected function setUp(): void
    {
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->pixelService = new PixelService($this->storeManager);
    }

    public function testGeneratePixelUrlCreatesEncodedUrl(): void
    {
        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')
            ->with(UrlInterface::URL_TYPE_WEB)
            ->willReturn('https://shop.com/');
        $this->storeManager->method('getStore')->willReturn($store);

        $data = ['action' => 'test'];
        $url = $this->pixelService->generatePixelUrl($data);

        $this->assertStringStartsWith('https://shop.com/tapbuy/pixel/track?data=', $url);
        $encodedPart = str_replace('https://shop.com/tapbuy/pixel/track?data=', '', $url);
        $decoded = json_decode(base64_decode($encodedPart), true);
        $this->assertSame('test', $decoded['action']);
    }

    public function testGeneratePixelDataReturnsExpectedStructure(): void
    {
        $data = $this->pixelService->generatePixelData('cart-123', ['id' => 'test-id'], 'redirect_check');

        $this->assertSame('cart-123', $data['cart_id']);
        $this->assertSame('test-id', $data['test_id']);
        $this->assertSame('redirect_check', $data['action']);
        $this->assertSame('test-id', $data['variation_id']);
        $this->assertFalse($data['remove_test_cookie']);
        $this->assertIsInt($data['timestamp']);
    }

    public function testGeneratePixelDataSetsRemoveCookieWhenNoTestId(): void
    {
        $data = $this->pixelService->generatePixelData('cart-123', []);

        $this->assertNull($data['test_id']);
        $this->assertTrue($data['remove_test_cookie']);
    }
}
