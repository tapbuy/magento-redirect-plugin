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

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Tapbuy\RedirectTracking\Api\Cart\CartResolverInterface;

/**
 * Cart Resolver
 *
 * Resolves cart entities from cart IDs by handling both masked and numeric IDs.
 * Provides methods to convert masked quote IDs to their numeric equivalents,
 * load cart data from the repository and retrieve masked cart IDs.
 *
 * Example Usage (constructor injection):
 * ```php
 * class SomeService
 * {
 *     /**
 *      * @var \Tapbuy\RedirectTracking\Api\Cart\CartResolverInterface
 *      */
 *     private $cartResolver;
 *
 *     public function __construct(
 *         \Tapbuy\RedirectTracking\Api\Cart\CartResolverInterface $cartResolver
 *     ) {
 *         $this->cartResolver = $cartResolver;
 *     }
 *
 *     public function execute(): void
 *     {
 *         // Load by masked ID
 *         $cart = $this->cartResolver->resolveAndLoadQuote('masked-id-abc123');
 *
 *         // Or resolve just the ID
 *         $cartId = $this->cartResolver->resolveCartId('masked-id-abc123');
 *
 *         // Or get the masked ID from a numeric ID
 *         $maskedId = $this->cartResolver->getMaskedCartId(123);
 *     }
 * }
 * ```
 */
class CartResolver implements CartResolverInterface
{
    /**
     * Service for converting masked quote IDs to numeric quote IDs
     *
     * @var MaskedQuoteIdToQuoteIdInterface
     */
    private $maskedQuoteIdToQuoteId;

    /**
     * Repository for loading and managing cart/quote entities
     *
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * Factory for creating quote ID mask models (for reverse lookup: numeric -> masked)
     *
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * CartResolver constructor.
     *
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId Service for masked ID conversion
     * @param CartRepositoryInterface $cartRepository Repository for cart data access
     * @param QuoteIdMaskFactory $quoteIdMaskFactory Factory for reverse masked ID lookup
     */
    public function __construct(
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        CartRepositoryInterface $cartRepository,
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->cartRepository = $cartRepository;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
    }

    /**
     * Resolve cart ID and load the corresponding cart/quote from repository.
     *
     * Converts masked or numeric cart IDs to their numeric form and retrieves
     * the complete cart object from the repository.
     *
     * @param string|int $cartId The cart ID (masked or numeric)
     * @return CartInterface The loaded cart/quote entity
     * @throws \Magento\Framework\Exception\NoSuchEntityException If cart not found
     * @throws \Magento\Framework\Exception\InvalidArgumentException If masked ID is invalid
     */
    public function resolveAndLoadQuote(string|int $cartId): CartInterface
    {
        $realCartId = $this->resolveCartId($cartId);
        return $this->cartRepository->get($realCartId);
    }

    /**
     * Resolve a cart ID from its masked or numeric form.
     *
     * Accepts both masked quote IDs (typically used in GraphQL APIs) and
     * numeric quote IDs. Returns the numeric ID in both cases.
     *
     * @param string|int $cartId The cart ID to resolve (masked or numeric)
     * @return int The numeric cart/quote ID
     * @throws \Magento\Framework\Exception\InvalidArgumentException If masked ID is invalid
     *
     * @example
     * ```php
     * // Numeric input - returned as-is
     * $id = $resolver->resolveCartId('123');
     * // Result: 123
     *
     * // Masked input - converted to numeric
     * $id = $resolver->resolveCartId('abc123def456');
     * // Result: 456 (or relevant numeric ID)
     * ```
     */
    public function resolveCartId(string|int $cartId): int
    {
        // Normalize int to string for consistent handling
        $cartId = (string) $cartId;
        
        // If it's a pure numeric ID (only digits), return as-is
        // Use ctype_digit to avoid false positives like "1e3", "+10", "10.0"
        if (ctype_digit($cartId)) {
            return (int) $cartId;
        }

        // Use native Magento interface for masked ID conversion
        return $this->maskedQuoteIdToQuoteId->execute($cartId);
    }

    /**
     * Get the masked cart ID for a numeric cart/quote ID.
     *
     * @param int $cartId The numeric cart/quote ID
     * @return string|null The masked cart ID, or null if not found
     */
    public function getMaskedCartId(int $cartId): ?string
    {
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'quote_id');
        return $quoteIdMask->getMaskedId() ?: null;
    }
}
