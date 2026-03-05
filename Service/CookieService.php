<?php

declare(strict_types=1);

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
     * Update the A/B test ID cookie.
     *
     * Sets the cookie when an ID is provided, removes it otherwise.
     *
     * @param string|null $id
     * @return void
     */
    public function updateABTestCookie(?string $id): void
    {
        if ($id !== null) {
            $this->setABTestIdCookie($id);
        } else {
            $this->removeABTestIdCookie();
        }
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

        // Seed with exact-name CookieManager lookups first
        foreach ($cookieNames as $cookieName) {
            $cookieValue = $this->cookieManager->getCookie($cookieName);
            if ($cookieValue !== null) {
                $this->trackingCookies[$cookieName] = $cookieValue;
            }
        }

        // Collect prefix-matched cookies from browser and injected sources.
        // $_COOKIE takes precedence over $this->cookies when the same key appears in both.
        $sources = array_replace(
            $this->filterCookiesByPrefix($this->cookies, $cookieNames),
            $this->filterCookiesByPrefix($_COOKIE, $cookieNames)
        );

        foreach ($sources as $name => $value) {
            if (!isset($this->trackingCookies[$name])) {
                $this->trackingCookies[$name] = $value;
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

        foreach ($this->filterCookiesByPrefix($_COOKIE, $cookiePrefixes) as $name => $value) {
            $this->logger->debug('getStoreCookies matched', ['cookieName' => $name, 'cookieValue' => $value]);
            $this->storeCookies[$name] = $value;
        }

        // Injected cookies override browser cookies
        foreach ($this->filterCookiesByPrefix($this->cookies, $cookiePrefixes) as $name => $value) {
            $this->logger->debug('getStoreCookies customCookies', ['cookieName' => $name, 'cookieValue' => $value]);
            $this->storeCookies[$name] = $value;
        }

        return $this->storeCookies;
    }

    /**
     * Filter an array of cookies keeping only entries whose names start with one of the given prefixes.
     *
     * @param array $source  Source array of cookie name => value pairs
     * @param array $prefixes List of allowed name prefixes
     * @return array Filtered array containing only matching entries
     */
    private function filterCookiesByPrefix(array $source, array $prefixes): array
    {
        $result = [];
        foreach ($source as $name => $value) {
            foreach ($prefixes as $prefix) {
                if (strpos($name, $prefix) === 0) {
                    $result[$name] = $value;
                    break;
                }
            }
        }
        return $result;
    }
}
