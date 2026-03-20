<?php

declare(strict_types=1);

namespace Tapbuy\RedirectTracking\Test\Unit\Model\Authorization;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;
use Tapbuy\RedirectTracking\Model\Authorization\CartOwnershipValidator;

class CartOwnershipValidatorTest extends TestCase
{
    private CartOwnershipValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new CartOwnershipValidator();
    }

    public function testGuestCanAccessGuestCart(): void
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('getCustomerId')->willReturn(null);

        // Should not throw
        $this->validator->validateOwnership($quote, null);
        $this->assertTrue(true);
    }

    public function testGuestCannotAccessCustomerCart(): void
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('getCustomerId')->willReturn(42);

        $this->expectException(LocalizedException::class);
        $this->validator->validateOwnership($quote, null);
    }

    public function testCustomerCanAccessOwnCart(): void
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('getCustomerId')->willReturn(42);

        $this->validator->validateOwnership($quote, 42);
        $this->assertTrue(true);
    }

    public function testCustomerCannotAccessOtherCustomerCart(): void
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('getCustomerId')->willReturn(42);

        $this->expectException(LocalizedException::class);
        $this->validator->validateOwnership($quote, 99);
    }

    public function testCustomerCannotAccessGuestCart(): void
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('getCustomerId')->willReturn(null);

        $this->expectException(LocalizedException::class);
        $this->validator->validateOwnership($quote, 42);
    }
}
