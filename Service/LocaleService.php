<?php

/**
 * Tapbuy Locale Service
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Service;

use Magento\Framework\Locale\Resolver;
use Tapbuy\RedirectTracking\Api\ConfigInterface;

class LocaleService
{
    /**
     * @var Resolver
     */
    private $localeResolver;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * LocaleService constructor.
     *
     * @param Resolver $localeResolver
     * @param ConfigInterface $config
     */
    public function __construct(
        Resolver $localeResolver,
        ConfigInterface $config
    ) {
        $this->localeResolver = $localeResolver;
        $this->config = $config;
    }

    /**
     * Get locale based on configuration
     *
     * @return string
     */
    public function getLocale(): string
    {
        $locale = $this->localeResolver->getLocale();

        if ($this->config->getLocaleFormat() === 'short') {
            return $this->formatLocaleToShort($locale);
        }

        return $locale;
    }

    /**
     * Format locale to short format (e.g., 'en' instead of 'en_US')
     *
     * @param string $locale
     * @return string
     */
    private function formatLocaleToShort(string $locale): string
    {
        if (strpos($locale, '_') !== false) {
            return substr($locale, 0, strpos($locale, '_'));
        }
        if (strpos($locale, '-') !== false) {
            return substr($locale, 0, strpos($locale, '-'));
        }
        return substr($locale, 0, 2);
    }
}
