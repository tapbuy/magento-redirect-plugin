<?php

declare(strict_types=1);

namespace Tapbuy\RedirectTracking\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\RedirectTracking\Api\ConfigInterface;
use Tapbuy\RedirectTracking\Model\Config;

class ConfigTest extends TestCase
{
    private Config $config;
    private ScopeConfigInterface&MockObject $scopeConfig;
    private EncryptorInterface&MockObject $encryptor;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->encryptor = $this->createMock(EncryptorInterface::class);
        $this->config = new Config($this->scopeConfig, $this->encryptor);
    }

    public function testIsEnabledReturnsTrue(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with(Config::XML_PATH_ENABLED, 'store', null)
            ->willReturn(true);

        $this->assertTrue($this->config->isEnabled());
    }

    public function testIsEnabledWithStoreId(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with(Config::XML_PATH_ENABLED, 'store', 5)
            ->willReturn(false);

        $this->assertFalse($this->config->isEnabled(5));
    }

    public function testGetApiUrl(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(Config::XML_PATH_API_URL, 'store', null)
            ->willReturn('https://api.tapbuy.io');

        $this->assertSame('https://api.tapbuy.io', $this->config->getApiUrl());
    }

    public function testGetEncryptionKeyDecryptsValue(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(Config::XML_PATH_ENCRYPTION_KEY, 'store', null)
            ->willReturn('encrypted_value');

        $this->encryptor->method('decrypt')
            ->with('encrypted_value')
            ->willReturn('decrypted_key');

        $this->assertSame('decrypted_key', $this->config->getEncryptionKey());
    }

    public function testGetLocaleFormatDefaultsToLong(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(Config::XML_PATH_LOCALE_FORMAT, 'store', null)
            ->willReturn(null);

        $this->assertSame('long', $this->config->getLocaleFormat());
    }

    public function testGetLocaleFormatReturnsConfiguredValue(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(Config::XML_PATH_LOCALE_FORMAT, 'store', null)
            ->willReturn('short');

        $this->assertSame('short', $this->config->getLocaleFormat());
    }

    public function testGetOrderConfirmationModeDefaultsToGraphql(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(Config::XML_PATH_ORDER_CONFIRMATION_MODE, 'store', null)
            ->willReturn(null);

        $this->assertSame(ConfigInterface::ORDER_CONFIRMATION_MODE_GRAPHQL, $this->config->getOrderConfirmationMode());
    }

    public function testGetScrubbingKeysUrl(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(Config::XML_PATH_SCRUBBING_KEYS_URL, 'default')
            ->willReturn('https://keys.example.com');

        $this->assertSame('https://keys.example.com', $this->config->getScrubbingKeysUrl());
    }
}
