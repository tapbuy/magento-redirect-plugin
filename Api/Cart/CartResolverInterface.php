<?php

declare(strict_types=1);

/**
 * Cart Resolver Interface
 *
 * Resolves cart entities from cart IDs.
 * Handles masked ID conversion and quote loading.
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Api\Cart;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Cart Resolver
 *
 * Resolves cart entities from cart IDs by handling both masked and numeric IDs.
 * Provides methods to convert masked quote IDs to their numeric equivalents,
 * load cart data from the repository and retrieve masked cart IDs.
 *
 * Example Usage:
 * ```php
 * use Tapbuy\RedirectTracking\Api\Cart\CartResolverInterface;
 *
 * class SomeService
 * {
 *     public function __construct(
 *         private readonly CartResolverInterface $cartResolver
 *     ) {
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
interface CartResolverInterface
{
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
    public function resolveCartId(string|int $cartId): int;

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
    public function resolveAndLoadQuote(string|int $cartId): CartInterface;

    /**
     * Get the masked cart ID for a numeric cart/quote ID.
     *
     * Converts a numeric quote ID to its masked equivalent.
     * Returns null if no masked ID exists for the given quote.
     *
     * @param int $cartId The numeric cart/quote ID
     * @return string|null The masked cart ID, or null if not found
     */
    public function getMaskedCartId(int $cartId): ?string;
}
