<?php

declare(strict_types=1);

/**
 * Cart Ownership Validator
 *
 * Validates that a cart belongs to the authenticated customer.
 * Ensures customers cannot access carts that don't belong to them.
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Model\Authorization;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Quote\Model\Quote;

class CartOwnershipValidator
{
    /**
     * Validates that a quote belongs to the caller.
     *
     * Two tiers of validation:
     *  - Guest caller (no customerId): only guest carts (no customer_id on the quote) are
     *    accessible. This prevents numeric quote ID enumeration against customer carts.
     *  - Authenticated caller: the quote must belong to that exact customer. Guest carts
     *    and other customers' carts are both rejected.
     *
     * Note: guest-to-guest access via masked UUID is intentionally allowed — the 128-bit
     * UUID acts as a bearer token, consistent with Magento's guest cart model.
     *
     * @param Quote $quote The quote to validate
     * @param int|null $customerId The authenticated customer ID (null for guest)
     * @return void
     * @throws LocalizedException If the quote does not belong to the caller
     */
    public function validateOwnership(Quote $quote, ?int $customerId): void
    {
        $quoteCustomerId = $quote->getCustomerId() ? (int) $quote->getCustomerId() : null;

        if (!$customerId) {
            // Guest caller: block access to customer-owned carts (prevents numeric ID enumeration)
            if ($quoteCustomerId !== null) {
                throw new LocalizedException(
                    new Phrase('Cart does not belong to the current customer.')
                );
            }
            return;
        }

        // Authenticated caller: must match the quote's customer exactly
        if ($quoteCustomerId !== $customerId) {
            throw new LocalizedException(
                new Phrase('Cart does not belong to the current customer.')
            );
        }
    }
}
