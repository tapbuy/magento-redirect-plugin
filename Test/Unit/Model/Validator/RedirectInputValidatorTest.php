<?php

declare(strict_types=1);

namespace Tapbuy\RedirectTracking\Test\Unit\Model\Validator;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Tapbuy\RedirectTracking\Helper\JsonDecodeHelper;
use Tapbuy\RedirectTracking\Model\Validator\RedirectInputValidator;

class RedirectInputValidatorTest extends TestCase
{
    private RedirectInputValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new RedirectInputValidator(new JsonDecodeHelper());
    }

    public function testThrowsWhenCartIdMissing(): void
    {
        $this->expectException(LocalizedException::class);

        $this->validator->validate([]);
    }

    public function testThrowsWhenCartIdEmpty(): void
    {
        $this->expectException(LocalizedException::class);

        $this->validator->validate(['cart_id' => '']);
    }

    public function testReturnsNormalizedInputWithCartId(): void
    {
        $result = $this->validator->validate(['cart_id' => 'abc123']);

        $this->assertSame('abc123', $result['cart_id']);
        $this->assertSame([], $result['cookies']);
        $this->assertNull($result['force_redirect']);
        $this->assertNull($result['referer']);
    }

    public function testParsesCookiesFromJsonString(): void
    {
        $cookies = json_encode(['ga' => '123']);
        $result = $this->validator->validate([
            'cart_id' => 'abc',
            'cookies' => $cookies,
        ]);

        $this->assertSame(['ga' => '123'], $result['cookies']);
    }

    public function testExtractsOptionalParameters(): void
    {
        $result = $this->validator->validate([
            'cart_id' => 'abc',
            'force_redirect' => 'true',
            'referer' => 'https://example.com',
        ]);

        $this->assertSame('true', $result['force_redirect']);
        $this->assertSame('https://example.com', $result['referer']);
    }
}
