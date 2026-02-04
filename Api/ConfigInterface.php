<?php

/**
 * Tapbuy Configuration Interface
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Api;

/**
 * Interface ConfigInterface
 *
 * Provides access to Tapbuy configuration settings.
 */
interface ConfigInterface
{
    /**
     * Check if Tapbuy is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled($storeId = null);

    /**
     * Check if mobile redirection is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isMobileRedirectionEnabled($storeId = null);

    /**
     * Check if desktop redirection is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isDesktopRedirectionEnabled($storeId = null);

    /**
     * Get Tapbuy API URL
     *
     * @param int|null $storeId
     * @return string
     */
    public function getApiUrl($storeId = null);

    /**
     * Get Tapbuy API Key
     *
     * @param int|null $storeId
     * @return string
     */
    public function getApiKey($storeId = null);

    /**
     * Get Tapbuy Encryption Key (Decrypted)
     *
     * @param int|null $storeId
     * @return string
     */
    public function getEncryptionKey($storeId = null);

    /**
     * Check if gifting is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isGiftingEnabled($storeId = null);

    /**
     * Get Gifting URL
     *
     * @param int|null $storeId
     * @return string
     */
    public function getGiftingUrl($storeId = null);

    /**
     * Get Locale Format (long|short)
     *
     * @param int|null $storeId
     * @return string
     */
    public function getLocaleFormat($storeId = null);
}
