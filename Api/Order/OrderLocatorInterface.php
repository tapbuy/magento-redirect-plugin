<?php

declare(strict_types=1);

/**
 * Order Locator Interface
 *
 * Provides centralized order lookup functionality for Tapbuy modules.
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Api\Order;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Interface OrderLocatorInterface
 *
 * Provides order lookup functionality supporting multiple identifier types.
 */
interface OrderLocatorInterface
{
    public const IDENTIFIER_TYPE_AUTO = 'auto';
    public const IDENTIFIER_TYPE_ENTITY_ID = 'entity_id';
    public const IDENTIFIER_TYPE_INCREMENT_ID = 'increment_id';

    /**
     * Retrieve order by ID or increment ID.
     *
     * Supports multiple identifier types:
     * - entity_id: The database primary key
     * - increment_id: The human-readable order number (e.g., "100000001")
     * - auto: Tries increment_id first, then entity_id
     *
     * @param string $identifier The order identifier
     * @param string $identifierType The type of identifier (auto, entity_id, increment_id)
     * @return OrderInterface
     * @throws NoSuchEntityException If order not found
     */
    public function getByIdentifier(
        string $identifier,
        string $identifierType = self::IDENTIFIER_TYPE_AUTO
    ): OrderInterface;
}
