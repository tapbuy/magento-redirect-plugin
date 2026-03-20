<?php

declare(strict_types=1);

namespace Tapbuy\RedirectTracking\Test\Unit\Model;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\Cookie\PublicCookieMetadata;
use Magento\Framework\Stdlib\CookieManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\RedirectTracking\Api\CookieInterface;
use Tapbuy\RedirectTracking\Model\Cookie;

class CookieTest extends TestCase
{
    private Cookie $cookie;
    private CookieManagerInterface&MockObject $cookieManager;
    private CookieMetadataFactory&MockObject $cookieMetadataFactory;
    private SessionManagerInterface&MockObject $sessionManager;
    private Http&MockObject $request;

    protected function setUp(): void
    {
        $this->cookieManager = $this->createMock(CookieManagerInterface::class);
        $this->cookieMetadataFactory = $this->createMock(CookieMetadataFactory::class);
        $this->sessionManager = $this->createMock(SessionManagerInterface::class);
        $this->request = $this->createMock(Http::class);

        $this->cookie = new Cookie(
            $this->cookieManager,
            $this->cookieMetadataFactory,
            $this->sessionManager,
            $this->request
        );
    }

    public function testSetCookieCreatesPublicCookieWithDefaults(): void
    {
        $metadata = $this->createMock(PublicCookieMetadata::class);
        $metadata->method('setDuration')->willReturnSelf();
        $metadata->method('setPath')->willReturnSelf();
        $metadata->method('setDomain')->willReturnSelf();
        $metadata->method('setHttpOnly')->with(true)->willReturnSelf();
        $metadata->method('setSecure')->willReturnSelf();

        $this->cookieMetadataFactory->method('createPublicCookieMetadata')->willReturn($metadata);
        $this->sessionManager->method('getCookiePath')->willReturn('/');
        $this->sessionManager->method('getCookieDomain')->willReturn('.example.com');

        $this->cookieManager->expects($this->once())
            ->method('setPublicCookie')
            ->with('test_cookie', 'test_value', $metadata);

        $this->cookie->setCookie('test_cookie', 'test_value');
    }

    public function testSetCookieWithCustomDuration(): void
    {
        $metadata = $this->createMock(PublicCookieMetadata::class);
        $metadata->method('setDuration')->with(3600)->willReturnSelf();
        $metadata->method('setPath')->willReturnSelf();
        $metadata->method('setDomain')->willReturnSelf();
        $metadata->method('setHttpOnly')->willReturnSelf();
        $metadata->method('setSecure')->willReturnSelf();

        $this->cookieMetadataFactory->method('createPublicCookieMetadata')->willReturn($metadata);
        $this->sessionManager->method('getCookiePath')->willReturn('/');
        $this->sessionManager->method('getCookieDomain')->willReturn('.example.com');

        $this->cookie->setCookie('test', 'val', true, true, 3600);
        $this->assertTrue(true);
    }

    public function testRemoveCookie(): void
    {
        $metadata = $this->createMock(PublicCookieMetadata::class);
        $metadata->method('setPath')->willReturnSelf();
        $metadata->method('setDomain')->willReturnSelf();
        $metadata->method('setHttpOnly')->willReturnSelf();
        $metadata->method('setSecure')->willReturnSelf();

        $this->cookieMetadataFactory->method('createPublicCookieMetadata')->willReturn($metadata);
        $this->sessionManager->method('getCookiePath')->willReturn('/');
        $this->sessionManager->method('getCookieDomain')->willReturn('.example.com');

        $this->cookieManager->expects($this->once())
            ->method('deleteCookie')
            ->with('test_cookie', $metadata);

        $this->cookie->removeCookie('test_cookie');
    }

    public function testGetCookieDelegatesToManager(): void
    {
        $this->cookieManager->method('getCookie')->with('test_name')->willReturn('test_value');
        $this->assertSame('test_value', $this->cookie->getCookie('test_name'));
    }

    public function testGetCookieReturnsNullWhenMissing(): void
    {
        $this->cookieManager->method('getCookie')->with('missing')->willReturn(null);
        $this->assertNull($this->cookie->getCookie('missing'));
    }

    public function testSetABTestIdCookieSetsNonHttpOnly(): void
    {
        $this->request->method('isSecure')->willReturn(false);

        $metadata = $this->createMock(PublicCookieMetadata::class);
        $metadata->method('setDuration')->willReturnSelf();
        $metadata->method('setPath')->willReturnSelf();
        $metadata->method('setDomain')->willReturnSelf();
        $metadata->expects($this->once())->method('setHttpOnly')->with(false)->willReturnSelf();
        $metadata->method('setSecure')->willReturnSelf();

        $this->cookieMetadataFactory->method('createPublicCookieMetadata')->willReturn($metadata);
        $this->sessionManager->method('getCookiePath')->willReturn('/');
        $this->sessionManager->method('getCookieDomain')->willReturn('.example.com');

        $this->cookieManager->expects($this->once())
            ->method('setPublicCookie')
            ->with(CookieInterface::COOKIE_NAME_ABTEST_ID, 'abc123', $metadata);

        $this->cookie->setABTestIdCookie('abc123');
    }

    public function testGetABTestIdReturnsValue(): void
    {
        $this->cookieManager->method('getCookie')
            ->with(CookieInterface::COOKIE_NAME_ABTEST_ID)
            ->willReturn('test-id-123');

        $this->assertSame('test-id-123', $this->cookie->getABTestId());
    }
}
