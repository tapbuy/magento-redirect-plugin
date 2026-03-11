<?php

declare(strict_types=1);

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
    public const ORDER_CONFIRMATION_MODE_GRAPHQL = 'graphql';
    public const ORDER_CONFIRMATION_MODE_OBSERVER = 'observer';
    public const ORDER_CONFIRMATION_MODE_BOTH = 'both';

    /**
     * Check if Tapbuy is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled($storeId = null);

    /**
     * Get Tapbuy API URL
     *
     * @param int|null $storeId
     * @return string
     */
    public function getApiUrl($storeId = null);

    /**
     * Get Tapbuy Encryption Key (Decrypted)
     *
     * @param int|null $storeId
     * @return string
     */
    public function getEncryptionKey($storeId = null);

    /**
     * Get Locale Format (long|short)
     *
     * @param int|null $storeId
     * @return string
     */
    public function getLocaleFormat($storeId = null);

    /**
     * Get Order Confirmation Mode (graphql|observer|both)
     *
     * @param int|null $storeId
     * @return string
     */
    public function getOrderConfirmationMode($storeId = null);
}
