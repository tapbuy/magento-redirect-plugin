<?php

declare(strict_types=1);

/**
 * Tapbuy Redirect and Tracking Helper
 *
 * Coordinates core functionality through focused service classes.
 * This class provides a facade to the underlying services and maintains
 * backward compatibility with the DataHelperInterface.
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\State;
use Magento\Framework\HTTP\Header;
use Magento\Framework\App\RequestInterface;
use Tapbuy\RedirectTracking\Api\DataHelperInterface;
use Tapbuy\RedirectTracking\Service\CookieService;
use Tapbuy\RedirectTracking\Service\EncryptionService;
use Tapbuy\RedirectTracking\Service\LocaleService;
use Tapbuy\RedirectTracking\Service\PixelService;

class Data extends AbstractHelper implements DataHelperInterface
{
    /**
     * @var CookieService
     */
    private $cookieService;

    /**
     * @var EncryptionService
     */
    private $encryptionService;

    /**
     * @var LocaleService
     */
    private $localeService;

    /**
     * @var PixelService
     */
    private $pixelService;

    /**
     * @var State
     */
    private $appState;

    /**
     * @var Header
     */
    private $httpHeader;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * Data constructor.
     *
     * @param Context $context
     * @param CookieService $cookieService
     * @param EncryptionService $encryptionService
     * @param LocaleService $localeService
     * @param PixelService $pixelService
     * @param State $appState
     * @param Header $httpHeader
     * @param RequestInterface $request
     */
    public function __construct(
        Context $context,
        CookieService $cookieService,
        EncryptionService $encryptionService,
        LocaleService $localeService,
        PixelService $pixelService,
        State $appState,
        Header $httpHeader,
        RequestInterface $request
    ) {
        $this->cookieService = $cookieService;
        $this->encryptionService = $encryptionService;
        $this->localeService = $localeService;
        $this->pixelService = $pixelService;
        $this->appState = $appState;
        $this->httpHeader = $httpHeader;
        $this->request = $request;
        parent::__construct($context);
    }

    /**
     * Get current path with query string
     *
     * @return string
     */
    public function getCurrentPath()
    {
        return $this->request->getRequestUri();
    }

    /**
     * Get locale
     *
     * @return string
     */
    public function getLocale()
    {
        return $this->localeService->getLocale();
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
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
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
        return $this->cookieService->getABTestId();
    }

    /**
     * Set A/B test ID cookie
     *
     * @param string $value
     * @return void
     */
    public function setABTestIdCookie($value)
    {
        $this->cookieService->setABTestIdCookie($value);
    }

    /**
     * Remove A/B test ID cookie
     *
     * @return void
     */
    public function removeABTestIdCookie()
    {
        $this->cookieService->removeABTestIdCookie();
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
        $this->cookieService->setCookies($cookies);
    }

    /**
     * Retrieves an array of cookies.
     *
     * @return array An array containing the cookies.
     */
    public function getCookies(): array
    {
        return $this->cookieService->getCookies();
    }

    /**
     * Retrieves the value of a cookie by its name.
     *
     * Prefix matching is performed when the name does not start with '*'.
     * If the name starts with '*', no prefix matching is performed and an exact lookup is used.
     *
     * @param string $name The name of the cookie to retrieve.
     * @return string|null The value of the cookie if it exists, or null otherwise.
     */
    public function getCookie(string $name)
    {
        return $this->cookieService->getCookie($name);
    }

    /**
     * Get tracking cookies for Tapbuy
     *
     * @return array
     */
    public function getTrackingCookies()
    {
        return $this->cookieService->getTrackingCookies();
    }

    /**
     * Get store cookies for Tapbuy
     *
     * @return array
     */
    public function getStoreCookies()
    {
        return $this->cookieService->getStoreCookies();
    }

    /**
     * Get encrypted key for Tapbuy
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return string
     */
    public function getTapbuyKey($quote)
    {
        return $this->encryptionService->getTapbuyKey($quote);
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
        return $this->pixelService->generatePixelUrl($data);
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
        return $this->pixelService->generatePixelData($cartId, $testResult, $action);
    }
}
