<?php

/**
 * Token Authorization for GraphQL API
 *
 * Centralized token authorization for all Tapbuy modules.
 * Provides both simple authorization check and permission-based authorization.
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

        if (
            !in_array('Magento_Backend::admin', $permissions) &&
            !in_array('Magento_Backend::all', $permissions) &&
            !in_array($requiredResource, $permissions)
        ) {
            throw new GraphQlAuthorizationException(__('You do not have permission to access this resource.'));
        }
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
