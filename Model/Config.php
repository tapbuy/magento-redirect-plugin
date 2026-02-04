<?php

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
    public const XML_PATH_MOBILE_REDIRECT_ENABLED = 'tapbuy/general/mobile_redirection_enabled';
    public const XML_PATH_DESKTOP_REDIRECT_ENABLED = 'tapbuy/general/desktop_redirection_enabled';
    public const XML_PATH_API_URL = 'tapbuy/api/api_url';
    public const XML_PATH_API_KEY = 'tapbuy/api/api_key';
    public const XML_PATH_ENCRYPTION_KEY = 'tapbuy/api/encryption_key';
    public const XML_PATH_LOCALE_FORMAT = 'tapbuy/api/locale_format';
    public const XML_PATH_GIFTING_ENABLED = 'tapbuy/gifting/enabled';
    public const XML_PATH_GIFTING_URL = 'tapbuy/gifting/gifting_url';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * Config constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
    }

    /**
     * Check if Tapbuy is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled($storeId = null)
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if mobile redirection is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isMobileRedirectionEnabled($storeId = null)
    {
        if (!$this->isEnabled($storeId)) {
            return false;
        }

        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_MOBILE_REDIRECT_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if desktop redirection is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isDesktopRedirectionEnabled($storeId = null)
    {
        if (!$this->isEnabled($storeId)) {
            return false;
        }

        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_DESKTOP_REDIRECT_ENABLED,
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
    public function getApiUrl($storeId = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_API_URL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get Tapbuy API Key
     *
     * @param int|null $storeId
     * @return string
     */
    public function getApiKey($storeId = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_API_KEY,
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
    public function getEncryptionKey($storeId = null)
    {
        $encryptedKey = $this->scopeConfig->getValue(
            self::XML_PATH_ENCRYPTION_KEY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $this->encryptor->decrypt($encryptedKey);
    }

    /**
     * Check if gifting is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isGiftingEnabled($storeId = null)
    {
        if (!$this->isEnabled($storeId)) {
            return false;
        }

        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_GIFTING_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get Gifting URL
     *
     * @param int|null $storeId
     * @return string
     */
    public function getGiftingUrl($storeId = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_GIFTING_URL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get Locale Format (long|short)
     *
     * @param int|null $storeId
     * @return string
     */
    public function getLocaleFormat($storeId = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_LOCALE_FORMAT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'long';
    }
}
