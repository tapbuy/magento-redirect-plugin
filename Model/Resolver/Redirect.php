<?php

namespace Tapbuy\RedirectTracking\Model\Resolver;

use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Tapbuy\RedirectTracking\Model\Service;
use Tapbuy\RedirectTracking\Model\Config;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

class Redirect implements ResolverInterface
{
    /**
     * @var Service
     */
    protected $service;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * Redirect constructor.
     *
     * Initializes the Redirect resolver with required dependencies.
     *
     * @param Service $service
     * @param Config $config
     * @param QuoteFactory $quoteFactory
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     */
    public function __construct(
        Service $service,
        Config $config,
        QuoteFactory $quoteFactory,
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        $this->service = $service;
        $this->config = $config;
        $this->quoteFactory = $quoteFactory;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
    }

    /**
     * Resolves the redirect tracking logic.
     *
     * This method is typically used as a resolver in a GraphQL context to handle
     * redirect tracking for Tapbuy. It processes the incoming request and returns
     * the appropriate redirect response.
     *
     * @param Field $field The GraphQL field being resolved.
     * @param ContextInterface $context The context of the GraphQL query.
     * @param ResolveInfo $info Metadata for the GraphQL query.
     * @param array|null $value The value passed from the parent resolver.
     * @param array|null $args The arguments provided in the GraphQL query.
     * @return mixed The result of the redirect tracking resolution.
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $input = $args['input'] ?? [];
        $cartId = $input['cart_id'];
        if (empty($cartId)) {
            throw new LocalizedException(new Phrase('Cart ID is required.'));
        }

        $forceRedirect = isset($input['force_redirect']) ? $input['force_redirect'] : null;

        if (!is_numeric($cartId)) {
            $maskedId = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
            $cartId = $maskedId->getQuoteId();
        }
        $quote = $this->quoteFactory->create()->load($cartId, 'entity_id');

        $customerId = $context->getUserId();
        if ($customerId) {
            if ($quote->getCustomerId() && $quote->getCustomerId() != $customerId) {
                throw new LocalizedException(new Phrase('Cart does not belong to the current customer.'));
            }
        }

        if (!$quote || !$quote->getId()) {
            return [
                'redirect' => false,
                'redirect_url' => null,
                'message' => 'Cart not found.'
            ];
        }

        if (!$this->config->isEnabled()) {
            return [
                'redirect' => false,
                'redirect_url' => '/checkout',
                'message' => 'Tapbuy is disabled.'
            ];
        }

        $result = $this->service->triggerABTest($quote, $forceRedirect);
        if ($result && isset($result['redirect']) && $result['redirect'] === true && isset($result['redirectURL'])) {
            return [
                'redirect' => true,
                'redirect_url' => $result['redirectURL'],
                'message' => 'Redirect to Tapbuy.'
            ];
        }

        return [
            'redirect' => false,
            'redirect_url' => '/checkout',
            'message' => 'Redirect to standard checkout.'
        ];
    }
}
