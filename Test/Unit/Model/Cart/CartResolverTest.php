<?php

declare(strict_types=1);

namespace Tapbuy\RedirectTracking\Test\Unit\Model\Cart;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\RedirectTracking\Model\Cart\CartResolver;

class CartResolverTest extends TestCase
{
    private CartResolver $cartResolver;
    private MaskedQuoteIdToQuoteIdInterface&MockObject $maskedQuoteIdToQuoteId;
    private CartRepositoryInterface&MockObject $cartRepository;
    private QuoteIdMaskFactory&MockObject $quoteIdMaskFactory;

    protected function setUp(): void
    {
        $this->maskedQuoteIdToQuoteId = $this->createMock(MaskedQuoteIdToQuoteIdInterface::class);
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->quoteIdMaskFactory = $this->createMock(QuoteIdMaskFactory::class);

        $this->cartResolver = new CartResolver(
            $this->maskedQuoteIdToQuoteId,
            $this->cartRepository,
            $this->quoteIdMaskFactory
        );
    }

    public function testResolveCartIdReturnsIntForNumericId(): void
    {
        $this->assertSame(42, $this->cartResolver->resolveCartId(42));
    }

    public function testResolveCartIdReturnsIntForNumericString(): void
    {
        $this->assertSame(42, $this->cartResolver->resolveCartId('42'));
    }

    public function testResolveCartIdResolvesMaskedId(): void
    {
        $this->maskedQuoteIdToQuoteId->method('execute')
            ->with('abc123')
            ->willReturn(42);

        $this->assertSame(42, $this->cartResolver->resolveCartId('abc123'));
    }

    public function testResolveAndLoadQuoteLoadsCart(): void
    {
        $cart = $this->createMock(CartInterface::class);
        $this->cartRepository->method('get')->with(42)->willReturn($cart);

        $result = $this->cartResolver->resolveAndLoadQuote(42);
        $this->assertSame($cart, $result);
    }

    public function testResolveAndLoadQuoteResolvesMaskedFirst(): void
    {
        $cart = $this->createMock(CartInterface::class);
        $this->maskedQuoteIdToQuoteId->method('execute')
            ->with('masked-id')
            ->willReturn(99);
        $this->cartRepository->method('get')->with(99)->willReturn($cart);

        $result = $this->cartResolver->resolveAndLoadQuote('masked-id');
        $this->assertSame($cart, $result);
    }

    public function testGetMaskedCartIdReturnsMaskedId(): void
    {
        $quoteIdMask = $this->createMock(QuoteIdMask::class);
        $quoteIdMask->method('load')->with(42, 'quote_id')->willReturnSelf();
        $quoteIdMask->method('getMaskedId')->willReturn('masked-abc');
        $this->quoteIdMaskFactory->method('create')->willReturn($quoteIdMask);

        $this->assertSame('masked-abc', $this->cartResolver->getMaskedCartId(42));
    }

    public function testGetMaskedCartIdReturnsNullWhenEmpty(): void
    {
        $quoteIdMask = $this->createMock(QuoteIdMask::class);
        $quoteIdMask->method('load')->with(42, 'quote_id')->willReturnSelf();
        $quoteIdMask->method('getMaskedId')->willReturn('');
        $this->quoteIdMaskFactory->method('create')->willReturn($quoteIdMask);

        $this->assertNull($this->cartResolver->getMaskedCartId(42));
    }
}
