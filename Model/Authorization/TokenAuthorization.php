<?php

/**
 * Token Authorization for GraphQL API
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Model\Authorization;

use Magento\Framework\App\RequestInterface;
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
     * Check if the request has a valid integration token
     *
     * @return bool
     */
    public function isAuthorized(): bool
    {
        $authHeader = $this->request->getHeader('Authorization');
        if (!$authHeader) {
            return false;
        }

        // Extract Bearer token
        if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return false;
        }

        $tokenString = $matches[1];

        try {
            $token = $this->tokenFactory->create()->loadByToken($tokenString);

            if (!$token->getId()) {
                return false;
            }

            // Check if token is for an integration (not customer or admin)
            $customerId = $token->getCustomerId();
            $adminId = $token->getAdminId();

            // Integration tokens have neither customer nor admin ID
            if ($customerId || $adminId) {
                return false;
            }

            // Verify the token is not revoked
            if ($token->getRevoked()) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
