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
    public function isEnabled(?int $storeId = null): bool;

    /**
     * Get Tapbuy API URL
     *
     * @param int|null $storeId
     * @return string
     */
    public function getApiUrl(?int $storeId = null): string;

    /**
     * Get Tapbuy Encryption Key (Decrypted)
     *
     * @param int|null $storeId
     * @return string
     */
    public function getEncryptionKey(?int $storeId = null): string;

    /**
     * Get Locale Format (long|short)
     *
     * @param int|null $storeId
     * @return string
     */
    public function getLocaleFormat(?int $storeId = null): string;

    /**
     * Get Order Confirmation Mode (graphql|observer|both)
     *
     * @param int|null $storeId
     * @return string
     */
    public function getOrderConfirmationMode(?int $storeId = null): string;
}
