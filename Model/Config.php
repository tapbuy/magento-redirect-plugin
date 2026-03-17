<?php

declare(strict_types=1);

/**
 * Tapbuy Redirect and Tracking Configuration Model
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use Tapbuy\RedirectTracking\Api\ConfigInterface;

class Config implements ConfigInterface
{
    public const XML_PATH_ENABLED = 'tapbuy/general/enabled';
    public const XML_PATH_API_URL = 'tapbuy/api/api_url';
    public const XML_PATH_ENCRYPTION_KEY = 'tapbuy/api/encryption_key';
    public const XML_PATH_LOCALE_FORMAT = 'tapbuy/api/locale_format';
    public const XML_PATH_ORDER_CONFIRMATION_MODE = 'tapbuy/tracking/order_confirmation_mode';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    /**
     * Check if Tapbuy is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get Tapbuy API URL
     *
     * @param int|null $storeId
     * @return string
     */
    public function getApiUrl(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_API_URL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get Tapbuy Encryption Key (Decrypted)
     *
     * @param int|null $storeId
     * @return string
     */
    public function getEncryptionKey(?int $storeId = null): string
    {
        $encryptedKey = $this->scopeConfig->getValue(
            self::XML_PATH_ENCRYPTION_KEY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $this->encryptor->decrypt($encryptedKey);
    }

    /**
     * Get Locale Format (long|short)
     *
     * @param int|null $storeId
     * @return string
     */
    public function getLocaleFormat(?int $storeId = null): string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_LOCALE_FORMAT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'long';
    }

    /**
     * Get Order Confirmation Mode (graphql|observer|both)
     *
     * @param int|null $storeId
     * @return string
     */
    public function getOrderConfirmationMode(?int $storeId = null): string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_ORDER_CONFIRMATION_MODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: ConfigInterface::ORDER_CONFIRMATION_MODE_GRAPHQL;
    }
}
