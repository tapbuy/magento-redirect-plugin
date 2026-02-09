<?php

declare(strict_types=1);

/**
 * Tapbuy Token Authorization Interface
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Api\Authorization;

use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;

/**
 * Interface TokenAuthorizationInterface
 *
 * Provides token-based authorization for GraphQL API.
 */
interface TokenAuthorizationInterface
{
    /**
     * Tapbuy super-admin resource - grants access to all Tapbuy resources
     */
    public const TAPBUY_SUPER_ADMIN = 'Tapbuy_RedirectTracking::tapbuy';

    /**
     * Tapbuy ACL Resources
     */
    public const TAPBUY_ORDER_VIEW = 'Tapbuy_RedirectTracking::order_view';
    public const TAPBUY_ORDER_EDIT = 'Tapbuy_RedirectTracking::order_edit';
    public const TAPBUY_ORDER_ASSIGN = 'Tapbuy_RedirectTracking::order_assign';
    public const TAPBUY_CART_UNLOCK = 'Tapbuy_RedirectTracking::cart_unlock';
    public const TAPBUY_CART_DEACTIVATE = 'Tapbuy_RedirectTracking::cart_deactivate';
    public const TAPBUY_CUSTOMER_SEARCH = 'Tapbuy_RedirectTracking::customer_search';
    public const TAPBUY_CUSTOMER_VIEW = 'Tapbuy_RedirectTracking::customer_view';
    public const TAPBUY_MODULES_VERSIONS = 'Tapbuy_RedirectTracking::modules_versions';
    public const TAPBUY_LOGS = 'Tapbuy_RedirectTracking::logs';

    /**
     * Get the token from the request.
     *
     * @return string
     * @throws GraphQlAuthorizationException
     */
    public function getToken(): string;

    /**
     * Check if the token has the required permission.
     *
     * Supports Tapbuy-specific ACL resources with backward compatibility:
     * - Checks for Magento super-admin resources (Magento_Backend::admin, Magento_Backend::all)
     * - Checks for Tapbuy super-admin resource (Tapbuy::tapbuy)
     * - Checks for parent Tapbuy resources that grant access to child resources
     * - Checks for the specific required resource
     * - (Backward compatibility) Checks for legacy Magento resources mapped to Tapbuy resources
     *
     * @param string $requiredResource The resource to check permissions for.
     * @throws GraphQlAuthorizationException If the token is invalid or lacks permissions.
     */
    public function authorize(string $requiredResource): void;

    /**
     * Check if the request has a valid integration token (simple check without permission verification)
     *
     * @return bool
     */
    public function isAuthorized(): bool;
}
