<?php

declare(strict_types=1);

namespace Tapbuy\RedirectTracking\Test\Unit\Service;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\Data\CartInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\RedirectTracking\Api\Cart\CartResolverInterface;
use Tapbuy\RedirectTracking\Api\ConfigInterface;
use Tapbuy\RedirectTracking\Service\EncryptionService;

class EncryptionServiceTest extends TestCase
{
    private EncryptionService $encryptionService;
    private ConfigInterface&MockObject $config;
    private Http&MockObject $request;
    private Json $json;
    private CartResolverInterface&MockObject $cartResolver;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigInterface::class);
        $this->request = $this->createMock(Http::class);
        $this->json = new Json();
        $this->cartResolver = $this->createMock(CartResolverInterface::class);

        $this->encryptionService = new EncryptionService(
            $this->config,
            $this->request,
            $this->json,
            $this->cartResolver
        );
    }

    public function testGetTapbuyKeyReturnsEmptyWhenNoEncryptionKey(): void
    {
        $this->config->method('getEncryptionKey')->willReturn('');
        $this->request->method('getHeader')->willReturn(false);

        $result = $this->encryptionService->getTapbuyKey(null);
        $this->assertSame('', $result);
    }

    public function testGetTapbuyKeyReturnsEncryptedBase64String(): void
    {
        $this->config->method('getEncryptionKey')->willReturn('test-encryption-key-32-chars!!!');
        $this->request->method('getHeader')->willReturn('Bearer my-session-token');

        $quote = $this->createMock(CartInterface::class);
        $quote->method('getId')->willReturn(42);
        $this->cartResolver->method('getMaskedCartId')->with(42)->willReturn('masked-id');

        $result = $this->encryptionService->getTapbuyKey($quote);

        // Result should be a valid base64 string
        $this->assertNotEmpty($result);
        $decoded = base64_decode($result, true);
        $this->assertNotFalse($decoded);
    }

    public function testGetTapbuyKeyIncludesSessionIdFromBearerToken(): void
    {
        $this->config->method('getEncryptionKey')->willReturn('test-encryption-key-32-chars!!!');
        $this->request->method('getHeader')
            ->with('Authorization')
            ->willReturn('Bearer my-token');

        $result = $this->encryptionService->getTapbuyKey(null);

        $this->assertNotEmpty($result);
    }

    public function testGetTapbuyKeyWorksWithNullQuote(): void
    {
        $this->config->method('getEncryptionKey')->willReturn('test-encryption-key-32-chars!!!');
        $this->request->method('getHeader')->willReturn(false);

        $result = $this->encryptionService->getTapbuyKey(null);

        // Even with no data, it should produce an encrypted result for empty JSON
        $this->assertNotEmpty($result);
    }
}
