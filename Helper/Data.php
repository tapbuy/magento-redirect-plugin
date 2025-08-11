<?php

/**
 * Tapbuy Redirect and Tracking Helper
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Helper;

use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\State;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\HTTP\Header;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Tapbuy\RedirectTracking\Model\Config;
use Tapbuy\RedirectTracking\Model\Cookie;
use Psr\Log\LoggerInterface;
use phpseclib3\Crypt\AES;

class Data extends AbstractHelper
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var CookieManagerInterface
     */
    private $cookieManager;

    /**
     * @var Cookie
     */
    private $cookie;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var Resolver
     */
    private $localeResolver;

    /**
     * @var Header
     */
    private $httpHeader;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @var State
     */
    private $appState;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Data constructor.
     *
     * @param Context $context
     * @param Config $config
     * @param CookieManagerInterface $cookieManager
     * @param Cookie $cookie
     * @param EncryptorInterface $encryptor
     * @param Json $json
     * @param Resolver $localeResolver
     * @param Header $httpHeader
     * @param RequestInterface $request
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param State $appState
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        Config $config,
        CookieManagerInterface $cookieManager,
        Cookie $cookie,
        EncryptorInterface $encryptor,
        Json $json,
        Resolver $localeResolver,
        Header $httpHeader,
        RequestInterface $request,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        State $appState,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->cookieManager = $cookieManager;
        $this->cookie = $cookie;
        $this->encryptor = $encryptor;
        $this->json = $json;
        $this->localeResolver = $localeResolver;
        $this->httpHeader = $httpHeader;
        $this->request = $request;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->appState = $appState;
        $this->logger = $logger;
        parent::__construct($context);
    }

    /**
     * Check if the request is from Tapbuy API
     *
     * @return bool
     */
    public function isTapbuyApiRequest()
    {
        $apiKey = $this->config->getApiKey();
        return $apiKey && $this->request->getHeader('x-tapbuy-key') === $apiKey;
    }

    /**
     * Get current path with query string
     *
     * @return string
     */
    public function getCurrentPath()
    {
        $path = $this->request->getRequestUri();
        return $path;
    }

    /**
     * Get locale
     *
     * @return string
     */
    public function getLocale()
    {
        return $this->localeResolver->getLocale();
    }

    /**
     * Get user agent
     *
     * @return string
     */
    public function getUserAgent()
    {
        return $this->httpHeader->getHttpUserAgent();
    }

    /**
     * Check if cart has products
     *
     * @return bool
     */
    public function hasProductsInCart($quote)
    {
        return $quote && $quote->getId() && count($quote->getAllVisibleItems()) > 0;
    }

    /**
     * Get A/B test ID from cookie
     *
     * @return string|null
     */
    public function getABTestId()
    {
        return $this->cookie->getABTestId();
    }

    /**
     * Set A/B test ID cookie
     *
     * @param string $value
     * @return void
     */
    public function setABTestIdCookie($value)
    {
        $this->cookie->setABTestIdCookie($value);
    }

    /**
     * Remove A/B test ID cookie
     *
     * @return void
     */
    public function removeABTestIdCookie()
    {
        $this->cookie->removeABTestIdCookie();
    }

    /**
     * Get tracking cookies for Tapbuy
     *
     * @return array
     */
    public function getTrackingCookies()
    {
        $trackingCookies = [];
        $cookieNames = ['_ga', '_pcid'];

        foreach ($cookieNames as $cookieName) {
            $cookieValue = $this->cookieManager->getCookie($cookieName);
            $this->logger->debug('getTrackingCookies cookieNames', ['cookieName' => $cookieName, 'cookieValue' => $cookieValue]);
            if ($cookieValue !== null) {
                $trackingCookies[$cookieName] = $cookieValue;
            }
        }

        // Handle cookies starting with '_ga'
        $allCookies = $_COOKIE; // Use PHP's global $_COOKIE array for additional checks
        foreach ($allCookies as $name => $value) {
            $this->logger->debug('getTrackingCookies allCookies', ['cookieName' => $name, 'cookieValue' => $value]);
            if (strpos($name, '_ga') === 0 && !isset($trackingCookies[$name])) {
                $trackingCookies[$name] = $value;
            }
        }

        return $trackingCookies;
    }

    /**
     * Get store cookies for Tapbuy
     *
     * @return array
     */
    public function getStoreCookies()
    {
        $storeCookies = [];
        $cookiePrefixes = ['PHPSESSID', 'form_key', 'mage-cache-', 'mage-messages'];

        foreach ($_COOKIE as $name => $value) {
            foreach ($cookiePrefixes as $prefix) {
                if (strpos($name, $prefix) === 0) {
                    $storeCookies[$name] = $value;
                    break;
                }
            }
        }

        return $storeCookies;
    }

    /**
     * Get encrypted key for Tapbuy
     *
     * @return string
     */
    public function getTapbuyKey($quote)
    {
        $data = [];

        // Get Authorization header from request
        $authorizationHeader = $this->request->getHeader('Authorization');
        if ($authorizationHeader) {
            // Extract Bearer token
            if (preg_match('/Bearer\s(\S+)/', $authorizationHeader, $matches)) {
                $data['session_id'] = $matches[1];
            }
        }

        // Get cart data if available
        if ($quote && $quote->getId()) {
            // Guest cart, retrieve masked ID
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load($quote->getId(), 'quote_id');
            $data['cart_id'] = $quoteIdMask->getMaskedId();
        }

        // Convert to JSON and encrypt
        $jsonData = $this->json->serialize($data);
        $encryptionKey = $this->config->getEncryptionKey();

        if (!$encryptionKey) {
            return '';
        }

        // Encrypt using AES
        $aes = new AES('ecb');
        $aes->setKey($encryptionKey);
        $aes->setKeyLength(256);
        $encryptedData = $aes->encrypt($jsonData);

        return base64_encode($encryptedData);
    }

    /**
     * Determines if the application is running in development mode.
     *
     * @return bool Returns true if the application is in development mode, false otherwise.
     */
    public function isDevelopmentMode(): bool
    {
        try {
            return $this->appState->getMode() === State::MODE_DEVELOPER;
        } catch (\Exception $e) {
            // If we can't determine mode, assume production for security
            return false;
        }
    }
}
