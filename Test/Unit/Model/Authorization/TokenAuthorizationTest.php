<?php

declare(strict_types=1);

namespace Tapbuy\RedirectTracking\Test\Unit\Model\Authorization;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\RedirectTracking\Api\Authorization\TokenAuthorizationInterface;
use Tapbuy\RedirectTracking\Model\Authorization\TokenAuthorization;

class TokenAuthorizationTest extends TestCase
{
    private TokenAuthorization $tokenAuth;
    private Http&MockObject $request;
    private UserContextInterface&MockObject $userContext;
    private AuthorizationInterface&MockObject $authorization;

    protected function setUp(): void
    {
        $this->request = $this->createMock(Http::class);
        $this->userContext = $this->createMock(UserContextInterface::class);
        $this->authorization = $this->createMock(AuthorizationInterface::class);

        $this->tokenAuth = new TokenAuthorization(
            $this->request,
            $this->userContext,
            $this->authorization
        );
    }

    public function testGetTokenThrowsWhenNoAuthHeader(): void
    {
        $this->request->method('getHeader')->with('Authorization')->willReturn(false);

        $this->expectException(GraphQlAuthorizationException::class);
        $this->tokenAuth->getToken();
    }

    public function testGetTokenThrowsOnInvalidFormat(): void
    {
        $this->request->method('getHeader')->with('Authorization')->willReturn('Basic abc');

        $this->expectException(GraphQlAuthorizationException::class);
        $this->tokenAuth->getToken();
    }

    public function testGetTokenReturnsBearerToken(): void
    {
        $this->request->method('getHeader')->with('Authorization')->willReturn('Bearer my-token-123');

        $this->assertSame('my-token-123', $this->tokenAuth->getToken());
    }

    public function testAuthorizeThrowsWhenNoUserId(): void
    {
        $this->request->method('getHeader')->with('Authorization')->willReturn('Bearer valid');
        $this->userContext->method('getUserType')->willReturn(UserContextInterface::USER_TYPE_ADMIN);
        $this->userContext->method('getUserId')->willReturn(null);

        $this->expectException(GraphQlAuthorizationException::class);
        $this->tokenAuth->authorize(TokenAuthorizationInterface::TAPBUY_ORDER_VIEW);
    }

    public function testAuthorizeThrowsForNonAdminUser(): void
    {
        $this->request->method('getHeader')->with('Authorization')->willReturn('Bearer valid');
        $this->userContext->method('getUserType')->willReturn(UserContextInterface::USER_TYPE_CUSTOMER);
        $this->userContext->method('getUserId')->willReturn(1);

        $this->expectException(GraphQlAuthorizationException::class);
        $this->tokenAuth->authorize(TokenAuthorizationInterface::TAPBUY_ORDER_VIEW);
    }

    public function testAuthorizeSucceedsForBackendAdmin(): void
    {
        $this->request->method('getHeader')->with('Authorization')->willReturn('Bearer valid');
        $this->userContext->method('getUserType')->willReturn(UserContextInterface::USER_TYPE_ADMIN);
        $this->userContext->method('getUserId')->willReturn(1);
        $this->authorization->method('isAllowed')
            ->willReturnCallback(function ($resource) {
                return $resource === 'Magento_Backend::admin';
            });

        // Should not throw
        $this->tokenAuth->authorize(TokenAuthorizationInterface::TAPBUY_ORDER_VIEW);
        $this->assertTrue(true);
    }

    public function testAuthorizeSucceedsWithDirectResourcePermission(): void
    {
        $this->request->method('getHeader')->with('Authorization')->willReturn('Bearer valid');
        $this->userContext->method('getUserType')->willReturn(UserContextInterface::USER_TYPE_ADMIN);
        $this->userContext->method('getUserId')->willReturn(1);
        $this->authorization->method('isAllowed')
            ->willReturnCallback(function ($resource) {
                return $resource === TokenAuthorizationInterface::TAPBUY_ORDER_VIEW;
            });

        $this->tokenAuth->authorize(TokenAuthorizationInterface::TAPBUY_ORDER_VIEW);
        $this->assertTrue(true);
    }

    public function testAuthorizeSucceedsWithSuperAdmin(): void
    {
        $this->request->method('getHeader')->with('Authorization')->willReturn('Bearer valid');
        $this->userContext->method('getUserType')->willReturn(UserContextInterface::USER_TYPE_ADMIN);
        $this->userContext->method('getUserId')->willReturn(1);
        $this->authorization->method('isAllowed')
            ->willReturnCallback(function ($resource) {
                return $resource === TokenAuthorizationInterface::TAPBUY_SUPER_ADMIN;
            });

        $this->tokenAuth->authorize(TokenAuthorizationInterface::TAPBUY_ORDER_VIEW);
        $this->assertTrue(true);
    }

    public function testAuthorizeThrowsWhenNoPermission(): void
    {
        $this->request->method('getHeader')->with('Authorization')->willReturn('Bearer valid');
        $this->userContext->method('getUserType')->willReturn(UserContextInterface::USER_TYPE_ADMIN);
        $this->userContext->method('getUserId')->willReturn(1);
        $this->authorization->method('isAllowed')->willReturn(false);

        $this->expectException(GraphQlAuthorizationException::class);
        $this->tokenAuth->authorize(TokenAuthorizationInterface::TAPBUY_ORDER_VIEW);
    }

    public function testAuthorizeSucceedsWithLegacyMapping(): void
    {
        $this->request->method('getHeader')->with('Authorization')->willReturn('Bearer valid');
        $this->userContext->method('getUserType')->willReturn(UserContextInterface::USER_TYPE_INTEGRATION);
        $this->userContext->method('getUserId')->willReturn(5);
        $this->authorization->method('isAllowed')
            ->willReturnCallback(function ($resource) {
                return $resource === 'Magento_Sales::actions_view';
            });

        $this->tokenAuth->authorize(TokenAuthorizationInterface::TAPBUY_ORDER_VIEW);
        $this->assertTrue(true);
    }

    public function testIsAuthorizedReturnsTrueForValidAdmin(): void
    {
        $this->request->method('getHeader')->with('Authorization')->willReturn('Bearer valid');
        $this->userContext->method('getUserType')->willReturn(UserContextInterface::USER_TYPE_ADMIN);
        $this->userContext->method('getUserId')->willReturn(1);

        $this->assertTrue($this->tokenAuth->isAuthorized());
    }

    public function testIsAuthorizedReturnsFalseWithoutToken(): void
    {
        $this->request->method('getHeader')->with('Authorization')->willReturn(false);

        $this->assertFalse($this->tokenAuth->isAuthorized());
    }

    public function testIsAuthorizedReturnsFalseForCustomerUser(): void
    {
        $this->request->method('getHeader')->with('Authorization')->willReturn('Bearer valid');
        $this->userContext->method('getUserType')->willReturn(UserContextInterface::USER_TYPE_CUSTOMER);
        $this->userContext->method('getUserId')->willReturn(1);

        $this->assertFalse($this->tokenAuth->isAuthorized());
    }
}
