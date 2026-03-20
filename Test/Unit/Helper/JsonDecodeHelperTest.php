<?php

declare(strict_types=1);

namespace Tapbuy\RedirectTracking\Test\Unit\Helper;

use PHPUnit\Framework\TestCase;
use Tapbuy\RedirectTracking\Helper\JsonDecodeHelper;

class JsonDecodeHelperTest extends TestCase
{
    private JsonDecodeHelper $helper;

    protected function setUp(): void
    {
        $this->helper = new JsonDecodeHelper();
    }

    public function testReturnsArrayInputDirectly(): void
    {
        $this->assertSame(['key' => 'value'], $this->helper->decodeToArray(['key' => 'value']));
    }

    public function testReturnsEmptyArrayForNull(): void
    {
        $this->assertSame([], $this->helper->decodeToArray(null));
    }

    public function testReturnsEmptyArrayForEmptyString(): void
    {
        $this->assertSame([], $this->helper->decodeToArray(''));
    }

    public function testReturnsEmptyArrayForNonStringNonArray(): void
    {
        $this->assertSame([], $this->helper->decodeToArray(123));
    }

    public function testDecodesValidJsonString(): void
    {
        $this->assertSame(['a' => 1], $this->helper->decodeToArray('{"a":1}'));
    }

    public function testReturnsEmptyArrayForInvalidJson(): void
    {
        $this->assertSame([], $this->helper->decodeToArray('{bad'));
    }

    public function testReturnsEmptyArrayForNonArrayJson(): void
    {
        $this->assertSame([], $this->helper->decodeToArray('"just a string"'));
    }

    public function testDecodesBase64EncodedJson(): void
    {
        $encoded = base64_encode('{"key":"value"}');
        $this->assertSame(['key' => 'value'], $this->helper->decodeToArray($encoded, true));
    }

    public function testReturnsEmptyArrayForInvalidBase64(): void
    {
        $this->assertSame([], $this->helper->decodeToArray('!!!invalid!!!', true));
    }
}
