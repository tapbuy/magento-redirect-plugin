<?php

declare(strict_types=1);

namespace Tapbuy\RedirectTracking\Test\Unit\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\RedirectTracking\Api\ConfigInterface;
use Tapbuy\RedirectTracking\Api\DataHelperInterface;
use Tapbuy\RedirectTracking\Api\TapbuyServiceInterface;
use Tapbuy\RedirectTracking\Model\Authorization\CartOwnershipValidator;
use Tapbuy\RedirectTracking\Model\Cart\CartResolver;
use Tapbuy\RedirectTracking\Model\Resolver\Redirect;
use Tapbuy\RedirectTracking\Model\Validator\RedirectInputValidator;

class RedirectTest extends TestCase
{
    private Redirect $resolver;
    private TapbuyServiceInterface&MockObject $service;
    private ConfigInterface&MockObject $config;
    private DataHelperInterface&MockObject $helper;
    private CartResolver&MockObject $cartResolver;
    private CartOwnershipValidator&MockObject $cartOwnershipValidator;
    private RedirectInputValidator&MockObject $inputValidator;
    private Field&MockObject $field;
    private ContextInterface&MockObject $context;
    private ResolveInfo&MockObject $info;

    protected function setUp(): void
    {
        $this->service = $this->createMock(TapbuyServiceInterface::class);
        $this->config = $this->createMock(ConfigInterface::class);
        $this->helper = $this->createMock(DataHelperInterface::class);
        $this->cartResolver = $this->createMock(CartResolver::class);
        $this->cartOwnershipValidator = $this->createMock(CartOwnershipValidator::class);
        $this->inputValidator = $this->createMock(RedirectInputValidator::class);

        $this->resolver = new Redirect(
            $this->service,
            $this->config,
            $this->helper,
            $this->cartResolver,
            $this->cartOwnershipValidator,
            $this->inputValidator
        );

        $this->field = $this->createMock(Field::class);
        $this->context = $this->getMockBuilder(ContextInterface::class)
            ->addMethods(['getUserId'])
            ->getMockForAbstractClass();
        $this->info = $this->createMock(ResolveInfo::class);
    }

    public function testReturnsErrorWhenDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);

        $result = $this->resolver->resolve($this->field, $this->context, $this->info);

        $this->assertFalse($result['redirect']);
        $this->assertSame('/checkout', $result['redirect_url']);
        $this->assertStringContainsString('disabled', $result['message']);
    }

    public function testReturnsRedirectWhenServiceReturnsRedirect(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $this->inputValidator->method('validate')->willReturn([
            'cart_id' => 'abc',
            'cookies' => [],
            'force_redirect' => null,
            'referer' => null,
        ]);

        $quote = $this->createMock(Quote::class);
        $quote->method('getId')->willReturn(1);
        $this->cartResolver->method('resolveAndLoadQuote')->willReturn($quote);
        $this->context->method('getUserId')->willReturn(null);

        $this->service->method('triggerABTest')->willReturn([
            'redirect' => true,
            'redirectURL' => 'https://tapbuy.com/checkout',
            'id' => 'test-id',
        ]);

        $this->helper->method('generatePixelData')->willReturn(['action' => 'test']);
        $this->helper->method('generatePixelUrl')->willReturn('https://shop.com/pixel');

        $result = $this->resolver->resolve($this->field, $this->context, $this->info, null, [
            'input' => ['cart_id' => 'abc'],
        ]);

        $this->assertTrue($result['redirect']);
        $this->assertSame('https://tapbuy.com/checkout', $result['redirect_url']);
        $this->assertSame('https://shop.com/pixel', $result['pixel_url']);
    }

    public function testReturnsNoRedirectWhenServiceDeclines(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $this->inputValidator->method('validate')->willReturn([
            'cart_id' => 'abc',
            'cookies' => [],
            'force_redirect' => null,
            'referer' => null,
        ]);

        $quote = $this->createMock(Quote::class);
        $quote->method('getId')->willReturn(1);
        $this->cartResolver->method('resolveAndLoadQuote')->willReturn($quote);
        $this->context->method('getUserId')->willReturn(null);

        $this->service->method('triggerABTest')->willReturn(['redirect' => false]);

        $this->helper->method('generatePixelData')->willReturn([]);
        $this->helper->method('generatePixelUrl')->willReturn('https://shop.com/pixel');

        $result = $this->resolver->resolve($this->field, $this->context, $this->info, null, [
            'input' => ['cart_id' => 'abc'],
        ]);

        $this->assertFalse($result['redirect']);
        $this->assertSame('/checkout', $result['redirect_url']);
    }

    public function testReturnsErrorWhenQuoteHasNoId(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $this->inputValidator->method('validate')->willReturn([
            'cart_id' => 'abc',
            'cookies' => [],
            'force_redirect' => null,
            'referer' => null,
        ]);

        $quote = $this->createMock(Quote::class);
        $quote->method('getId')->willReturn(null);
        $this->cartResolver->method('resolveAndLoadQuote')->willReturn($quote);
        $this->context->method('getUserId')->willReturn(null);

        $result = $this->resolver->resolve($this->field, $this->context, $this->info, null, [
            'input' => ['cart_id' => 'abc'],
        ]);

        $this->assertFalse($result['redirect']);
        $this->assertStringContainsString('Cart not found', $result['message']);
    }
}
