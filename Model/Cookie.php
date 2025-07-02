<?php

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

class Cookie
{
    public const COOKIE_NAME_ABTEST_ID = 'tb-abtest-id';
    public const COOKIE_DURATION = 86400; // 1 day

    /**
     * @var CookieManagerInterface
     */
    private $cookieManager;

    /**
     * @var CookieMetadataFactory
     */
    private $cookieMetadataFactory;

    /**
     * @var SessionManagerInterface
     */
    private $sessionManager;

    /**
     * Cookie constructor.
     *
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param SessionManagerInterface $sessionManager
     */
    public function __construct(
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory,
        SessionManagerInterface $sessionManager
    ) {
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->sessionManager = $sessionManager;
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
    public function setCookie($name, $value, $httpOnly = true, $secure = true, $duration = null)
    {
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
    public function removeCookie($name, $httpOnly = true, $secure = true)
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
    public function getCookie($name)
    {
        return $this->cookieManager->getCookie($name);
    }

    /**
     * Set A/B test ID cookie
     *
     * @param string $value
     * @return void
     */
    public function setABTestIdCookie($value)
    {
        $this->setCookie(self::COOKIE_NAME_ABTEST_ID, $value, true, true);
    }

    /**
     * Remove A/B test ID cookie
     *
     * @return void
     */
    public function removeABTestIdCookie()
    {
        $this->removeCookie(self::COOKIE_NAME_ABTEST_ID, true, true);
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
