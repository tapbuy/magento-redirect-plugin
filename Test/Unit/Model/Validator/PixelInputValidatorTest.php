<?php

declare(strict_types=1);

namespace Tapbuy\RedirectTracking\Test\Unit\Model\Validator;

use PHPUnit\Framework\TestCase;
use Tapbuy\RedirectTracking\Helper\JsonDecodeHelper;
use Tapbuy\RedirectTracking\Model\Validator\PixelInputValidator;

class PixelInputValidatorTest extends TestCase
{
    private PixelInputValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PixelInputValidator(new JsonDecodeHelper());
    }

    public function testIsInputSizeValidAcceptsSmallInput(): void
    {
        $this->assertTrue($this->validator->isInputSizeValid('short'));
    }

    public function testIsInputSizeValidRejectsLargeInput(): void
    {
        $this->assertFalse($this->validator->isInputSizeValid(str_repeat('a', 2049)));
    }

    public function testIsInputSizeValidAcceptsExactlyMaxBytes(): void
    {
        $this->assertTrue($this->validator->isInputSizeValid(str_repeat('a', 2048)));
    }

    public function testDecodeAndSanitizeFiltersUnknownFields(): void
    {
        $data = ['action' => 'redirect', 'unknown_field' => 'bad', 'cart_id' => 'abc123'];
        $encoded = base64_encode(json_encode($data));

        $result = $this->validator->decodeAndSanitize($encoded);

        $this->assertArrayHasKey('action', $result);
        $this->assertArrayHasKey('cart_id', $result);
        $this->assertArrayNotHasKey('unknown_field', $result);
    }

    public function testDecodeAndSanitizeCoercesTypes(): void
    {
        $data = ['timestamp' => 12345, 'remove_test_cookie' => true];
        $encoded = base64_encode(json_encode($data));

        $result = $this->validator->decodeAndSanitize($encoded);

        $this->assertSame(12345, $result['timestamp']);
        $this->assertTrue($result['remove_test_cookie']);
    }

    public function testDecodeAndSanitizeStripsLogInjectionChars(): void
    {
        $data = ['action' => "test\ninjection\r"];
        $encoded = base64_encode(json_encode($data));

        $result = $this->validator->decodeAndSanitize($encoded);

        $this->assertSame('testinjection', $result['action']);
    }

    public function testDecodeAndSanitizeTruncatesLongStrings(): void
    {
        $data = ['action' => str_repeat('a', 300)];
        $encoded = base64_encode(json_encode($data));

        $result = $this->validator->decodeAndSanitize($encoded);

        $this->assertSame(255, strlen($result['action']));
    }

    public function testSanitizeCookieValueStripsInjectionChars(): void
    {
        $sanitized = $this->validator->sanitizeCookieValue("value\nwith\rnewlines\0");

        $this->assertSame('valuewithnewlines', $sanitized);
    }

    public function testDecodeAndSanitizeRejectsNonScalarStringValues(): void
    {
        $data = ['action' => ['nested' => 'array']];
        $encoded = base64_encode(json_encode($data));

        $result = $this->validator->decodeAndSanitize($encoded);

        $this->assertArrayNotHasKey('action', $result);
    }

    public function testDecodeAndSanitizeRejectsInvalidTimestamp(): void
    {
        $data = ['timestamp' => 'not_a_number'];
        $encoded = base64_encode(json_encode($data));

        $result = $this->validator->decodeAndSanitize($encoded);

        $this->assertArrayNotHasKey('timestamp', $result);
    }
}
