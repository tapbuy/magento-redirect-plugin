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
use Magento\Store\Model\StoreManagerInterface;
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
     * @var StoreManagerInterface
     */
    private $storeManager;

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
     * @param StoreManagerInterface $storeManager
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
        StoreManagerInterface $storeManager,
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
        $this->storeManager = $storeManager;
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
        $locale = $this->localeResolver->getLocale();
        if ($this->config->getLocaleFormat() === 'short') {
            if (strpos($locale, '_') !== false) {
                return substr($locale, 0, strpos($locale, '_'));
            }
            if (strpos($locale, '-') !== false) {
                return substr($locale, 0, strpos($locale, '-'));
            }
            return substr($locale, 0, 2);
        }
        return $locale;
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
        $cookie = $this->cookie->getABTestId();

        if (!$cookie) {
            $cookie = $this->getCookie(Cookie::COOKIE_NAME_ABTEST_ID);
        }

        return $cookie ?? null;
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
     * Sets multiple cookies based on the provided associative array.
     *
     * @param array $cookies An associative array where the key is the cookie name and the value is the cookie value.
     *
     * @return void
     */
    public function setCookies(array $cookies)
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
     * Wildcard matching is supported by suffixing the name with '*'.
     *
     * @param string $name The name of the cookie to retrieve.
     * @return string|null The value of the cookie if it exists, or null otherwise.
     */
    public function getCookie(string $name)
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
    public function getTrackingCookies()
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
    public function getStoreCookies()
    {
        $cookiePrefixes = ['PHPSESSID', 'form_key', 'mage-cache-', 'mage-messages'];

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
                    $this->logger->debug('getStoreCookies customCookies', ['cookieName' => $name, 'cookieValue' => $value]);
                    $this->storeCookies[$name] = $value;
                    break;
                }
            }
        }

        return $this->storeCookies;
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

    /**
     * Generate pixel tracking URL for headless frontends
     *
     * @param array $data
     * @return string
     */
    public function generatePixelUrl(array $data = []): string
    {
        $baseUrl = $this->storeManager->getStore()->getBaseUrl();
        $encodedData = base64_encode(json_encode($data));

        return $baseUrl . 'tapbuy/pixel/track?data=' . $encodedData;
    }

    /**
     * Generate pixel data for A/B test tracking
     *
     * @param string $cartId
     * @param array $testResult
     * @param string $action
     * @return array
     */
    public function generatePixelData(string $cartId, array $testResult = [], string $action = 'redirect_check'): array
    {
        return [
            'cart_id' => $cartId,
            'test_id' => $testResult['id'] ?? null,
            'action' => $action,
            'timestamp' => time(),
            'variation_id' => $testResult['variation_id'] ?? null,
            'remove_test_cookie' => empty($testResult['id']) ? true : false
        ];
    }
}
