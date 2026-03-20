<?php

declare(strict_types=1);

namespace Tapbuy\RedirectTracking\Test\Unit\Service;

use Magento\Framework\HTTP\PhpEnvironment\Request;
use Magento\Framework\Stdlib\Cookie\CookieReaderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tapbuy\RedirectTracking\Api\CookieInterface;
use Tapbuy\RedirectTracking\Service\CookieService;

class CookieServiceTest extends TestCase
{
    private CookieService $cookieService;
    private CookieReaderInterface&MockObject $cookieReader;
    private Request&MockObject $request;
    private CookieInterface&MockObject $cookie;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->cookieReader = $this->createMock(CookieReaderInterface::class);
        $this->request = $this->createMock(Request::class);
        $this->cookie = $this->createMock(CookieInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->cookieService = new CookieService(
            $this->cookieReader,
            $this->request,
            $this->cookie,
            $this->logger
        );
    }

    public function testGetABTestIdFromCookieModel(): void
    {
        $this->cookie->method('getABTestId')->willReturn('abtest-123');

        $this->assertSame('abtest-123', $this->cookieService->getABTestId());
    }

    public function testGetABTestIdReturnsNullWhenNotFound(): void
    {
        $this->cookie->method('getABTestId')->willReturn(null);

        $this->assertNull($this->cookieService->getABTestId());
    }

    public function testUpdateABTestCookieSetsWhenIdProvided(): void
    {
        $this->cookie->expects($this->once())
            ->method('setABTestIdCookie')
            ->with('new-id');

        $this->cookieService->updateABTestCookie('new-id');
    }

    public function testUpdateABTestCookieRemovesWhenNull(): void
    {
        $this->cookie->expects($this->once())
            ->method('removeABTestIdCookie');

        $this->cookieService->updateABTestCookie(null);
    }

    public function testSetAndGetCookies(): void
    {
        $cookies = ['key1' => 'val1', 'key2' => 'val2'];
        $this->cookieService->setCookies($cookies);

        $this->assertSame($cookies, $this->cookieService->getCookies());
    }

    public function testGetCookieMatchesExactName(): void
    {
        $this->cookieService->setCookies(['my_cookie' => 'my_value']);
        $this->assertSame('my_value', $this->cookieService->getCookie('my_cookie'));
    }

    public function testGetCookieReturnsNullForMissing(): void
    {
        $this->cookieService->setCookies([]);
        $this->assertNull($this->cookieService->getCookie('missing'));
    }

    public function testGetTrackingCookiesReturnsGaAndPcid(): void
    {
        $this->cookieReader->method('getCookie')
            ->willReturnMap([
                ['_ga', null, 'GA-123'],
                ['_pcid', null, 'PCID-456'],
            ]);

        $headers = $this->createMock(\Laminas\Http\Headers::class);
        $headers->method('get')->with('Cookie')->willReturn(false);
        $this->request->method('getHeaders')->willReturn($headers);

        $result = $this->cookieService->getTrackingCookies();

        $this->assertSame('GA-123', $result['_ga']);
        $this->assertSame('PCID-456', $result['_pcid']);
    }
}
