<?php

/**
 * Token Authorization for GraphQL API
 *
 * Centralized token authorization for all Tapbuy modules.
 * Provides both simple authorization check and permission-based authorization.
 *
 * Supports Tapbuy-specific ACL resources with backward compatibility for legacy Magento resources.
 * During the transition period, both old Magento resources and new Tapbuy resources are accepted.
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Model\Authorization;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Integration\Model\Oauth\TokenFactory;
use Magento\Integration\Model\IntegrationService;

class TokenAuthorization
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
     * @var RequestInterface
     */
    private $request;

    /**
     * @var TokenFactory
     */
    private $tokenFactory;

    /**
     * @var IntegrationService
     */
    private $integrationService;

    /**
     * @param RequestInterface $request
     * @param TokenFactory $tokenFactory
     * @param IntegrationService $integrationService
     */
    public function __construct(
        RequestInterface $request,
        TokenFactory $tokenFactory,
        IntegrationService $integrationService
    ) {
        $this->request = $request;
        $this->tokenFactory = $tokenFactory;
        $this->integrationService = $integrationService;
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
    public function authorize(string $requiredResource): void
    {
        $token = $this->getToken();
        $tokenModel = $this->tokenFactory->create()->loadByToken($token);

        if (!$tokenModel->getId()) {
            throw new GraphQlAuthorizationException(__('Invalid token.'));
        }

        $consumerId = $tokenModel->getConsumerId();
        $integration = $this->integrationService->findByConsumerId($consumerId);

        if (!$integration->getId() || !$integration->getStatus()) {
            throw new GraphQlAuthorizationException(__('Invalid integration.'));
        }

        // Get integration permissions
        $permissions = $this->integrationService->getSelectedResources($integration->getId());

        // Check if authorized
        if (!$this->hasPermission($permissions, $requiredResource)) {
            throw new GraphQlAuthorizationException(__('You do not have permission to access this resource.'));
        }
    }

    /**
     * Check if the given permissions grant access to the required resource.
     *
     * @param array $permissions List of granted permissions
     * @param string $requiredResource The required resource
     * @return bool
     */
    private function hasPermission(array $permissions, string $requiredResource): bool
    {
        // Check for Magento super-admin resources
        if (in_array('Magento_Backend::admin', $permissions) || in_array('Magento_Backend::all', $permissions)) {
            return true;
        }

        // Check for Tapbuy super-admin resource
        if (in_array(self::TAPBUY_SUPER_ADMIN, $permissions)) {
            return true;
        }

        // Check for direct permission
        if (in_array($requiredResource, $permissions)) {
            return true;
        }

        // Check for parent resources that grant access to the required resource
        foreach (self::PARENT_RESOURCES as $parentResource => $childResources) {
            if (in_array($parentResource, $permissions) && in_array($requiredResource, $childResources)) {
                return true;
            }
        }

        // Backward compatibility: Check for legacy Magento resources
        if (isset(self::LEGACY_RESOURCE_MAPPING[$requiredResource])) {
            $legacyResource = self::LEGACY_RESOURCE_MAPPING[$requiredResource];
            if (in_array($legacyResource, $permissions)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the request has a valid integration token (simple check without permission verification)
     *
     * @return bool
     */
    public function isAuthorized(): bool
    {
        try {
            $token = $this->getToken();
            $tokenModel = $this->tokenFactory->create()->loadByToken($token);

            if (!$tokenModel->getId()) {
                return false;
            }

            // Check if token is for an integration (not customer or admin)
            $customerId = $tokenModel->getCustomerId();
            $adminId = $tokenModel->getAdminId();

            // Integration tokens have neither customer nor admin ID
            if ($customerId || $adminId) {
                return false;
            }

            // Verify the token is not revoked
            if ($tokenModel->getRevoked()) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
