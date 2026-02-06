<?php

declare(strict_types=1);

/**
 * Cart Resolver
 *
 * Resolves cart entities from cart IDs.
 * Handles masked ID conversion and quote loading.
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Model\Cart;

use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\Quote;

class CartResolver
{
    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * CartResolver constructor.
     *
     * @param QuoteFactory $quoteFactory
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     */
    public function __construct(
        QuoteFactory $quoteFactory,
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        $this->quoteFactory = $quoteFactory;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
    }

    /**
     * Resolves and loads a quote by cart ID.
     *
     * Handles both numeric (real) and masked cart IDs.
     * Performs masked-to-real ID conversion if needed before loading the quote.
     *
     * @param string|int $cartId The numeric cart ID or masked ID
     * @return Quote The loaded quote object (may be empty if not found)
     */
    public function resolveAndLoadQuote($cartId): Quote
    {
        $realCartId = $this->resolveCartId($cartId);
        return $this->loadQuote($realCartId);
    }

    /**
     * Resolves a cart ID from masked or numeric format.
     *
     * If the cart ID is numeric, it's returned as-is.
     * If the cart ID is a string (masked), it's converted to the real numeric ID.
     *
     * @param string|int $cartId The numeric cart ID or masked ID
     * @return string|int The resolved numeric cart ID
     */
    private function resolveCartId($cartId)
    {
        // If already numeric, return as-is
        if (is_numeric($cartId)) {
            return $cartId;
        }

        // Load masked ID and get the real quote ID
        $maskedId = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        return $maskedId->getQuoteId();
    }

    /**
     * Loads a quote by its numeric entity ID.
     *
     * @param string|int $cartId The numeric cart ID (entity_id)
     * @return Quote The loaded quote object (may be empty if not found)
     */
    private function loadQuote($cartId): Quote
    {
        return $this->quoteFactory->create()->load($cartId, 'entity_id');
    }
}
