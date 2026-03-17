<?php

declare(strict_types=1);

/**
 * Tapbuy Cookie Management Interface
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Api;

/**
 * Interface CookieInterface
 *
 * Provides cookie management functionality for Tapbuy.
 */
interface CookieInterface
{
    /**
     * Cookie name for A/B test ID
     */
    public const COOKIE_NAME_ABTEST_ID = 'tb-abtest-id';

    /**
     * Default cookie duration in seconds (1 day)
     */
    public const COOKIE_DURATION = 86400;
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
    ): void;

    /**
     * Remove cookie
     *
     * @param string $name Cookie name
     * @param bool $httpOnly HTTP only flag
     * @param bool $secure Secure flag
     * @return void
     */
    public function removeCookie(string $name, bool $httpOnly = true, bool $secure = true): void;

    /**
     * Get cookie value
     *
     * @param string $name Cookie name
     * @return string|null
     */
    public function getCookie(string $name): ?string;

    /**
     * Set A/B test ID cookie
     *
     * @param string $value
     * @return void
     */
    public function setABTestIdCookie(string $value): void;

    /**
     * Remove A/B test ID cookie
     *
     * @return void
     */
    public function removeABTestIdCookie();

    /**
     * Get A/B test ID from cookie
     *
     * @return string|null
     */
    public function getABTestId();
}
