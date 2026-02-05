<?php

/**
 * Tapbuy Cookie Management Service
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Service;

use Magento\Framework\Stdlib\CookieManagerInterface;
use Tapbuy\RedirectTracking\Api\CookieInterface;
use Psr\Log\LoggerInterface;

class CookieService
{
    /**
     * @var CookieManagerInterface
     */
    private $cookieManager;

    /**
     * @var CookieInterface
     */
    private $cookie;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var array
     */
    private $cookies = [];

    /**
     * @var array
     */
    private $trackingCookies = [];

    /**
     * @var array
     */
    private $storeCookies = [];

    /**
     * CookieService constructor.
     *
     * @param CookieManagerInterface $cookieManager
     * @param CookieInterface $cookie
     * @param LoggerInterface $logger
     */
    public function __construct(
        CookieManagerInterface $cookieManager,
        CookieInterface $cookie,
        LoggerInterface $logger
    ) {
        $this->cookieManager = $cookieManager;
        $this->cookie = $cookie;
        $this->logger = $logger;
    }

    /**
     * Get A/B test ID from cookie
     *
     * @return string|null
     */
    public function getABTestId(): ?string
    {
        $cookie = $this->cookie->getABTestId();

        if (!$cookie) {
            $cookie = $this->getCookie(CookieInterface::COOKIE_NAME_ABTEST_ID);
        }

        return $cookie ?? null;
    }

    /**
     * Set A/B test ID cookie
     *
     * @param string $value
     * @return void
     */
    public function setABTestIdCookie(string $value): void
    {
        $this->cookie->setABTestIdCookie($value);
    }

    /**
     * Remove A/B test ID cookie
     *
     * @return void
     */
    public function removeABTestIdCookie(): void
    {
        $this->cookie->removeABTestIdCookie();
    }

    /**
     * Sets multiple cookies based on the provided associative array.
     *
     * @param array $cookies An associative array where the key is the cookie name and the value is the cookie value.
     * @return void
     */
    public function setCookies(array $cookies): void
    {
        $this->cookies = $cookies;
    }

    /**
     * Retrieves an array of cookies.
     *
     * @return array An array containing the cookies.
     */
    public function getCookies(): array
    {
        return $this->cookies;
    }

    /**
     * Retrieves the value of a cookie by its name.
     *
     * Prefix matching is performed when the name does not start with '*'.
     * If the name starts with '*', no prefix matching is performed and an exact lookup is used.
     *
     * @param string $name The name of the cookie to retrieve.
     * @return string|null The value of the cookie if it exists, or null otherwise.
     */
    public function getCookie(string $name): ?string
    {
        if (strpos($name, '*') !== 0) {
            foreach ($this->cookies as $cookieName => $cookieValue) {
                if (strpos($cookieName, $name) === 0) {
                    return $cookieValue;
                }
            }
        }
        return $this->cookies[$name] ?? null;
    }

    /**
     * Get tracking cookies for Tapbuy
     *
     * @return array
     */
    public function getTrackingCookies(): array
    {
        $cookieNames = ['_ga', '_pcid'];

        foreach ($cookieNames as $cookieName) {
            $cookieValue = $this->cookieManager->getCookie($cookieName);
            if ($cookieValue !== null) {
                $this->trackingCookies[$cookieName] = $cookieValue;
            }
        }

        $allCookies = $_COOKIE;
        foreach ($allCookies as $name => $value) {
            foreach ($cookieNames as $cookieName) {
                if (strpos($name, $cookieName) === 0 && !isset($this->trackingCookies[$name])) {
                    $this->trackingCookies[$name] = $value;
                }
            }
        }

        foreach ($this->cookies as $name => $value) {
            foreach ($cookieNames as $cookieName) {
                if (strpos($name, $cookieName) === 0 && !isset($this->trackingCookies[$name])) {
                    $this->trackingCookies[$name] = $value;
                }
            }
        }

        return $this->trackingCookies;
    }

    /**
     * Get store cookies for Tapbuy
     *
     * @return array
     */
    public function getStoreCookies(): array
    {
        $cookiePrefixes = ['mage-cache-', 'mage-messages'];

        foreach ($_COOKIE as $name => $value) {
            foreach ($cookiePrefixes as $prefix) {
                if (strpos($name, $prefix) === 0) {
                    $this->logger->debug('getStoreCookies matched', ['cookieName' => $name, 'cookieValue' => $value]);
                    $this->storeCookies[$name] = $value;
                    break;
                }
            }
        }

        // Add/override with custom cookies if provided
        foreach ($this->cookies as $name => $value) {
            foreach ($cookiePrefixes as $prefix) {
                if (strpos($name, $prefix) === 0) {
                    $this->logger->debug(
                        'getStoreCookies customCookies',
                        ['cookieName' => $name, 'cookieValue' => $value]
                    );
                    $this->storeCookies[$name] = $value;
                    break;
                }
            }
        }

        return $this->storeCookies;
    }
}
