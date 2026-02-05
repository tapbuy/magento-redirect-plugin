<?php

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
     * Validates that a quote belongs to the authenticated customer.
     *
     * If a customer ID is provided in the context, this method verifies that
     * the quote's customer ID matches. If the quote belongs to a different
     * customer, a LocalizedException is thrown.
     *
     * @param Quote $quote The quote to validate
     * @param int|null $customerId The authenticated customer ID (null for guest)
     * @return void
     * @throws LocalizedException If the quote belongs to a different customer
     */
    public function validateOwnership(Quote $quote, ?int $customerId): void
    {
        // Only validate if there is an authenticated customer
        if (!$customerId) {
            return;
        }

        // If the quote has a customer ID and it doesn't match the authenticated user, deny access
        if ($quote->getCustomerId() && $quote->getCustomerId() != $customerId) {
            throw new LocalizedException(
                new Phrase('Cart does not belong to the current customer.')
            );
        }
    }
}
