<?php

/**
 * Tapbuy Redirect and Tracking Helper
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Helper;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\HTTP\Header;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Tapbuy\RedirectTracking\Model\Config;
use Tapbuy\RedirectTracking\Model\Cookie;
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
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var CustomerSession
     */
    private $customerSession;

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
     * Data constructor.
     *
     * @param Context $context
     * @param Config $config
     * @param CookieManagerInterface $cookieManager
     * @param Cookie $cookie
     * @param CheckoutSession $checkoutSession
     * @param CustomerSession $customerSession
     * @param EncryptorInterface $encryptor
     * @param Json $json
     * @param Resolver $localeResolver
     * @param Header $httpHeader
     * @param RequestInterface $request
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     */
    public function __construct(
        Context $context,
        Config $config,
        CookieManagerInterface $cookieManager,
        Cookie $cookie,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        EncryptorInterface $encryptor,
        Json $json,
        Resolver $localeResolver,
        Header $httpHeader,
        RequestInterface $request,
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        $this->config = $config;
        $this->cookieManager = $cookieManager;
        $this->cookie = $cookie;
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->encryptor = $encryptor;
        $this->json = $json;
        $this->localeResolver = $localeResolver;
        $this->httpHeader = $httpHeader;
        $this->request = $request;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
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
    public function hasProductsInCart()
    {
        $quote = $this->checkoutSession->getQuote();
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
            if ($cookieValue !== null) {
                $trackingCookies[$cookieName] = $cookieValue;
            }
        }

        // Handle cookies starting with '_ga'
        $allCookies = $_COOKIE; // Use PHP's global $_COOKIE array for additional checks
        foreach ($allCookies as $name => $value) {
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
    public function getTapbuyKey()
    {
        $data = [];

        // Get customer data if logged in
        if ($this->customerSession->isLoggedIn()) {
            $customer = $this->customerSession->getCustomer();
            $data['customer_id'] = $customer->getId();
            $data['email'] = $customer->getEmail();
        }

        // Get cart data if available
        $quote = $this->checkoutSession->getQuote();
        if ($quote && $quote->getId()) {
            if ($quote->getCustomerId()) {
                // Logged-in customer cart
                $data['cart_id'] = $quote->getId();
            } else {
                // Guest cart, retrieve masked ID
                $quoteIdMask = $this->quoteIdMaskFactory->create()->load($quote->getId(), 'quote_id');
                $data['cart_id'] = $quoteIdMask->getMaskedId();
            }
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
}
