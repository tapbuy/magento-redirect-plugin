<?php

namespace Tapbuy\RedirectTracking\Model\Resolver;

use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Tapbuy\RedirectTracking\Api\ConfigInterface;
use Tapbuy\RedirectTracking\Api\DataHelperInterface;
use Tapbuy\RedirectTracking\Api\TapbuyServiceInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

class Redirect implements ResolverInterface
{
    /**
     * @var TapbuyServiceInterface
     */
    protected $service;

    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var DataHelperInterface
     */
    protected $helper;

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
     * @param TapbuyServiceInterface $service
     * @param ConfigInterface $config
     * @param DataHelperInterface $helper
     * @param QuoteFactory $quoteFactory
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     */
    public function __construct(
        TapbuyServiceInterface $service,
        ConfigInterface $config,
        DataHelperInterface $helper,
        QuoteFactory $quoteFactory,
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        $this->service = $service;
        $this->config = $config;
        $this->helper = $helper;
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
        $cookies = [];
        if (isset($input['cookies'])) {
            if (is_string($input['cookies'])) {
                $decoded = json_decode($input['cookies'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $cookies = $decoded;
                }
            } elseif (is_array($input['cookies'])) {
                $cookies = $input['cookies'];
            }
        }
        $this->helper->setCookies($cookies);

        $forceRedirect = isset($input['force_redirect']) ? $input['force_redirect'] : null;
        $referer = isset($input['referer']) ? $input['referer'] : null;

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
                'message' => 'Cart not found.',
                'pixel_url' => null
            ];
        }

        if (!$this->config->isEnabled()) {
            return [
                'redirect' => false,
                'redirect_url' => '/checkout',
                'message' => 'Tapbuy is disabled.',
                'pixel_url' => null
            ];
        }

        $result = $this->service->triggerABTest($quote, $forceRedirect, $referer);

        // Generate pixel URL for headless frontend
        $pixelData = $this->helper->generatePixelData($args['input']['cart_id'], $result, 'redirect_check');
        $pixelUrl = $this->helper->generatePixelUrl($pixelData);

        if ($result && isset($result['redirect']) && $result['redirect'] === true && isset($result['redirectURL'])) {
            return [
                'redirect' => true,
                'redirect_url' => $result['redirectURL'],
                'message' => 'Redirect to Tapbuy.',
                'pixel_url' => $pixelUrl
            ];
        }

        return [
            'redirect' => false,
            'redirect_url' => '/checkout',
            'message' => 'Redirect to standard checkout.',
            'pixel_url' => $pixelUrl
        ];
    }
}
