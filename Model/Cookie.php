<?php

declare(strict_types=1);

/**
 * Tapbuy Redirect and Tracking Cookie Management
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Model;

use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\App\RequestInterface;
use Tapbuy\RedirectTracking\Api\CookieInterface;

class Cookie implements CookieInterface
{
    /**
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param SessionManagerInterface $sessionManager
     * @param RequestInterface $request
     */
    public function __construct(
        private readonly CookieManagerInterface $cookieManager,
        private readonly CookieMetadataFactory $cookieMetadataFactory,
        private readonly SessionManagerInterface $sessionManager,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * Set cookie
     *
     * @param string $name Cookie name
     * @param string $value Cookie value
     * @param bool $httpOnly HTTP only flag
     * @param bool $secure Secure flag
     * @param int|null $duration Cookie duration in seconds
     * @return void
     */
    public function setCookie(
        string $name,
        string $value,
        bool $httpOnly = true,
        bool $secure = true,
        ?int $duration = null
    ): void {

        $metadata = $this->cookieMetadataFactory->createPublicCookieMetadata()
            ->setDuration($duration ?: self::COOKIE_DURATION)
            ->setPath($this->sessionManager->getCookiePath())
            ->setDomain($this->sessionManager->getCookieDomain())
            ->setHttpOnly($httpOnly)
            ->setSecure($secure);

        $this->cookieManager->setPublicCookie($name, $value, $metadata);
    }

    /**
     * Remove cookie
     *
     * @param string $name Cookie name
     * @param bool $httpOnly HTTP only flag
     * @param bool $secure Secure flag
     * @return void
     */
    public function removeCookie(string $name, bool $httpOnly = true, bool $secure = true): void
    {
        $metadata = $this->cookieMetadataFactory->createPublicCookieMetadata()
            ->setPath($this->sessionManager->getCookiePath())
            ->setDomain($this->sessionManager->getCookieDomain())
            ->setHttpOnly($httpOnly)
            ->setSecure($secure);

        $this->cookieManager->deleteCookie($name, $metadata);
    }

    /**
     * Get cookie value
     *
     * @param string $name Cookie name
     * @return string|null
     */
    public function getCookie(string $name): ?string
    {
        return $this->cookieManager->getCookie($name);
    }

    /**
     * Determine if cookie should have Secure flag based on current request
     *
     * @return bool
     */
    private function isCookieSecure(): bool
    {
        return $this->request->isSecure();
    }

    /**
     * Set A/B test ID cookie
     *
     * Note: HttpOnly is disabled because JavaScript needs to read this cookie for
     * A/B test tracking, transaction analytics, and headless integration.
     * Secure flag is conditionally set based on HTTPS detection.
     *
     * @param string $value
     * @return void
     */
    public function setABTestIdCookie(string $value): void
    {
        $this->setCookie(self::COOKIE_NAME_ABTEST_ID, $value, false, $this->isCookieSecure());
    }

    /**
     * Remove A/B test ID cookie
     *
     * @return void
     */
    public function removeABTestIdCookie()
    {
        $this->removeCookie(self::COOKIE_NAME_ABTEST_ID, false, $this->isCookieSecure());
    }

    /**
     * Get A/B test ID from cookie
     *
     * @return string|null
     */
    public function getABTestId()
    {
        return $this->getCookie(self::COOKIE_NAME_ABTEST_ID);
    }
}
