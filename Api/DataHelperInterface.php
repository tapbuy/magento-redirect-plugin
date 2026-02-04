<?php

/**
 * Tapbuy Data Helper Interface
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Api;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Interface DataHelperInterface
 *
 * Provides helper utilities for Tapbuy operations.
 */
interface DataHelperInterface
{
    /**
     * Get current path with query string
     *
     * @return string
     */
    public function getCurrentPath();

    /**
     * Get locale
     *
     * @return string
     */
    public function getLocale();

    /**
     * Get user agent
     *
     * @return string
     */
    public function getUserAgent();

    /**
     * Check if cart has products
     *
     * @param CartInterface|null $quote
     * @return bool
     */
    public function hasProductsInCart($quote);

    /**
     * Get A/B test ID from cookie
     *
     * @return string|null
     */
    public function getABTestId();

    /**
     * Set A/B test ID cookie
     *
     * @param string $value
     * @return void
     */
    public function setABTestIdCookie($value);

    /**
     * Remove A/B test ID cookie
     *
     * @return void
     */
    public function removeABTestIdCookie();

    /**
     * Sets multiple cookies based on the provided associative array.
     *
     * @param array $cookies An associative array where the key is the cookie name and the value is the cookie value.
     * @return void
     */
    public function setCookies(array $cookies);

    /**
     * Retrieves an array of cookies.
     *
     * @return array An array containing the cookies.
     */
    public function getCookies(): array;

    /**
     * Retrieves the value of a cookie by its name.
     *
     * Wildcard matching is supported by suffixing the name with '*'.
     *
     * @param string $name The name of the cookie to retrieve.
     * @return string|null The value of the cookie if it exists, or null otherwise.
     */
    public function getCookie(string $name);

    /**
     * Get tracking cookies for Tapbuy
     *
     * @return array
     */
    public function getTrackingCookies();

    /**
     * Get store cookies for Tapbuy
     *
     * @return array
     */
    public function getStoreCookies();

    /**
     * Get encrypted key for Tapbuy
     *
     * @param CartInterface|null $quote
     * @return string
     */
    public function getTapbuyKey($quote);

    /**
     * Determines if the application is running in development mode.
     *
     * @return bool Returns true if the application is in development mode, false otherwise.
     */
    public function isDevelopmentMode(): bool;

    /**
     * Generate pixel tracking URL for headless frontends
     *
     * @param array $data
     * @return string
     */
    public function generatePixelUrl(array $data = []): string;

    /**
     * Generate pixel data for A/B test tracking
     *
     * @param string $cartId
     * @param array $testResult
     * @param string $action
     * @return array
     */
    public function generatePixelData(string $cartId, array $testResult = [], string $action = 'redirect_check'): array;
}
