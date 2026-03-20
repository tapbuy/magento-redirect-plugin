<?php

declare(strict_types=1);

namespace Tapbuy\RedirectTracking\Test\Unit\Service;

use Magento\Framework\Locale\Resolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\RedirectTracking\Api\ConfigInterface;
use Tapbuy\RedirectTracking\Service\LocaleService;

class LocaleServiceTest extends TestCase
{
    private LocaleService $localeService;
    private Resolver&MockObject $localeResolver;
    private ConfigInterface&MockObject $config;

    protected function setUp(): void
    {
        $this->localeResolver = $this->createMock(Resolver::class);
        $this->config = $this->createMock(ConfigInterface::class);

        $this->localeService = new LocaleService(
            $this->localeResolver,
            $this->config
        );
    }

    public function testGetLocaleReturnsFullLocaleForLongFormat(): void
    {
        $this->localeResolver->method('getLocale')->willReturn('en_US');
        $this->config->method('getLocaleFormat')->willReturn('long');

        $this->assertSame('en_US', $this->localeService->getLocale());
    }

    public function testGetLocaleReturnsShortFormatWithUnderscore(): void
    {
        $this->localeResolver->method('getLocale')->willReturn('en_US');
        $this->config->method('getLocaleFormat')->willReturn('short');

        $this->assertSame('en', $this->localeService->getLocale());
    }

    public function testGetLocaleReturnsShortFormatWithHyphen(): void
    {
        $this->localeResolver->method('getLocale')->willReturn('en-GB');
        $this->config->method('getLocaleFormat')->willReturn('short');

        $this->assertSame('en', $this->localeService->getLocale());
    }

    public function testGetLocaleReturnsShortFormatForTwoCharLocale(): void
    {
        $this->localeResolver->method('getLocale')->willReturn('fr');
        $this->config->method('getLocaleFormat')->willReturn('short');

        $this->assertSame('fr', $this->localeService->getLocale());
    }
}
