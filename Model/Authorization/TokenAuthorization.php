<?php

declare(strict_types=1);

/**
 * Token Authorization for GraphQL API
 *
 * Centralized token authorization for all Tapbuy modules.
 * Provides both simple authorization check and permission-based authorization.
 *
 * Supports Admin JWT tokens (short-lived, issued via POST /rest/V1/integration/admin/token)
 * with backward compatibility for legacy Integration OAuth tokens during the transition period.
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Model\Authorization;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Tapbuy\RedirectTracking\Api\Authorization\TokenAuthorizationInterface;

class TokenAuthorization implements TokenAuthorizationInterface
{
    /**
     * Mapping of Tapbuy resources to legacy Magento resources for backward compatibility.
     * During the transition period, either the Tapbuy resource OR the legacy resource will grant access.
     */
    private const LEGACY_RESOURCE_MAPPING = [
        self::TAPBUY_ORDER_VIEW => 'Magento_Sales::actions_view',
        self::TAPBUY_ORDER_EDIT => 'Magento_Sales::actions_edit',
        self::TAPBUY_ORDER_ASSIGN => 'Magento_Sales::actions_edit',
        self::TAPBUY_CART_UNLOCK => 'Magento_Sales::actions_edit',
        self::TAPBUY_CART_DEACTIVATE => 'Magento_Sales::actions_edit',
        self::TAPBUY_CUSTOMER_SEARCH => 'Magento_Customer::customer',
        self::TAPBUY_CUSTOMER_VIEW => 'Magento_Customer::customer',
        self::TAPBUY_MODULES_VERSIONS => 'Magento_Backend::admin',
        self::TAPBUY_LOGS => 'Magento_Backend::admin',
    ];

    /**
     * Parent resources that grant access to child resources
     */
    private const PARENT_RESOURCES = [
        self::TAPBUY_SUPER_ADMIN => [
            self::TAPBUY_ORDER_VIEW,
            self::TAPBUY_ORDER_EDIT,
            self::TAPBUY_ORDER_ASSIGN,
            self::TAPBUY_CART_UNLOCK,
            self::TAPBUY_CART_DEACTIVATE,
            self::TAPBUY_CUSTOMER_SEARCH,
            self::TAPBUY_CUSTOMER_VIEW,
            self::TAPBUY_MODULES_VERSIONS,
            self::TAPBUY_LOGS,
        ],
        'Tapbuy_RedirectTracking::order' => [
            self::TAPBUY_ORDER_VIEW,
            self::TAPBUY_ORDER_EDIT,
            self::TAPBUY_ORDER_ASSIGN,
        ],
        'Tapbuy_RedirectTracking::cart' => [
            self::TAPBUY_CART_UNLOCK,
            self::TAPBUY_CART_DEACTIVATE,
        ],
        'Tapbuy_RedirectTracking::customer' => [
            self::TAPBUY_CUSTOMER_SEARCH,
            self::TAPBUY_CUSTOMER_VIEW,
        ],
        'Tapbuy_RedirectTracking::system' => [
            self::TAPBUY_MODULES_VERSIONS,
            self::TAPBUY_LOGS,
        ],
    ];

    /**
     * User types accepted as valid callers.
     * Accepts both Admin JWT (USER_TYPE_ADMIN) and legacy Integration OAuth tokens (USER_TYPE_INTEGRATION)
     * to allow rolling deployment without downtime.
     */
    private const ACCEPTED_USER_TYPES = [
        UserContextInterface::USER_TYPE_ADMIN,
        UserContextInterface::USER_TYPE_INTEGRATION,
    ];

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var UserContextInterface
     */
    private $userContext;

    /**
     * @var AuthorizationInterface
     */
    private $authorization;

    /**
     * @param RequestInterface $request
     * @param UserContextInterface $userContext
     * @param AuthorizationInterface $authorization
     */
    public function __construct(
        RequestInterface $request,
        UserContextInterface $userContext,
        AuthorizationInterface $authorization
    ) {
        $this->request = $request;
        $this->userContext = $userContext;
        $this->authorization = $authorization;
    }

    /**
     * Get the token from the request.
     *
     * @return string
     * @throws GraphQlAuthorizationException
     */
    public function getToken(): string
    {
        $authHeader = $this->request->getHeader('Authorization');
        if (!$authHeader) {
            throw new GraphQlAuthorizationException(__('Token is required.'));
        }

        // Extract Bearer token
        if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            throw new GraphQlAuthorizationException(__('Invalid authorization header format.'));
        }

        return $matches[1];
    }

    /**
     * Check if the token has the required permission.
     *
     * Accepts both Admin JWT tokens and legacy Integration OAuth tokens (backward compatibility).
     * Token validation is delegated to Magento's UserContextInterface which is automatically
     * populated from the Authorization header by the GraphQL request pipeline.
     *
     * Permission checks:
     * - Magento all-resources grant (Magento_Backend::all)
     * - Tapbuy super-admin resource (Tapbuy_RedirectTracking::tapbuy)
     * - Parent Tapbuy resources that grant access to child resources
     * - The specific required resource
     * - (Backward compatibility) Legacy Magento resources mapped to Tapbuy resources
     *
     * @param string $requiredResource The resource to check permissions for.
     * @throws GraphQlAuthorizationException If the token is invalid or lacks permissions.
     */
    public function authorize(string $requiredResource): void
    {
        // Validate header format (throws if missing or malformed)
        $this->getToken();

        $userType = $this->userContext->getUserType();
        $userId = $this->userContext->getUserId();

        if (!$userId || !in_array($userType, self::ACCEPTED_USER_TYPES)) {
            throw new GraphQlAuthorizationException(
                __('A valid admin or integration token is required.')
            );
        }

        if (!$this->hasPermission($requiredResource)) {
            throw new GraphQlAuthorizationException(
                __('You do not have permission to access this resource.')
            );
        }
    }

    /**
     * Check if the current user has permission to access the required resource.
     *
     * Uses Magento's AuthorizationInterface which correctly resolves permissions
     * from the active UserContext for both admin JWTs and integration tokens.
     *
     * @param string $requiredResource The required resource
     * @return bool
     */
    private function hasPermission(string $requiredResource): bool
    {
        // Check for Magento super-admin resources
        if ($this->authorization->isAllowed('Magento_Backend::admin')
            || $this->authorization->isAllowed('Magento_Backend::all')
        ) {
            return true;
        }

        // Check for Tapbuy super-admin resource
        if ($this->authorization->isAllowed(self::TAPBUY_SUPER_ADMIN)) {
            return true;
        }

        // Check for direct permission
        if ($this->authorization->isAllowed($requiredResource)) {
            return true;
        }

        // Check for parent resources that grant access to the required resource
        foreach (self::PARENT_RESOURCES as $parentResource => $childResources) {
            if ($this->authorization->isAllowed($parentResource)
                && in_array($requiredResource, $childResources)
            ) {
                return true;
            }
        }

        // Backward compatibility: Check for legacy Magento resources
        if (isset(self::LEGACY_RESOURCE_MAPPING[$requiredResource])) {
            if ($this->authorization->isAllowed(self::LEGACY_RESOURCE_MAPPING[$requiredResource])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the request carries a valid admin or integration token (without permission check).
     *
     * @return bool
     */
    public function isAuthorized(): bool
    {
        try {
            // Validate header format
            $this->getToken();

            $userType = $this->userContext->getUserType();
            $userId = $this->userContext->getUserId();

            return $userId > 0 && in_array($userType, self::ACCEPTED_USER_TYPES);
        } catch (\Exception $e) {
            return false;
        }
    }
}
