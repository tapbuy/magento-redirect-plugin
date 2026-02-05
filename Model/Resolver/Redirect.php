<?php

namespace Tapbuy\RedirectTracking\Model\Resolver;

use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\Quote;
use Tapbuy\RedirectTracking\Api\ConfigInterface;
use Tapbuy\RedirectTracking\Api\DataHelperInterface;
use Tapbuy\RedirectTracking\Api\TapbuyServiceInterface;
use Tapbuy\RedirectTracking\Model\Authorization\CartOwnershipValidator;
use Tapbuy\RedirectTracking\Model\Cart\CartResolver;
use Tapbuy\RedirectTracking\Model\Validator\RedirectInputValidator;

class Redirect implements ResolverInterface
{
    /**
     * @var TapbuyServiceInterface
     */
    protected $service;

    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var DataHelperInterface
     */
    protected $helper;

    /**
     * @var CartResolver
     */
    private $cartResolver;

    /**
     * @var CartOwnershipValidator
     */
    private $cartOwnershipValidator;

    /**
     * @var RedirectInputValidator
     */
    private $inputValidator;

    /**
     * Redirect constructor.
     *
     * Initializes the Redirect resolver with required dependencies.
     *
     * @param TapbuyServiceInterface $service
     * @param ConfigInterface $config
     * @param DataHelperInterface $helper
     * @param CartResolver $cartResolver
     * @param CartOwnershipValidator $cartOwnershipValidator
     * @param RedirectInputValidator $inputValidator
     */
    public function __construct(
        TapbuyServiceInterface $service,
        ConfigInterface $config,
        DataHelperInterface $helper,
        CartResolver $cartResolver,
        CartOwnershipValidator $cartOwnershipValidator,
        RedirectInputValidator $inputValidator
    ) {
        $this->service = $service;
        $this->config = $config;
        $this->helper = $helper;
        $this->cartResolver = $cartResolver;
        $this->cartOwnershipValidator = $cartOwnershipValidator;
        $this->inputValidator = $inputValidator;
    }

    /**
     * Resolves the redirect tracking logic.
     *
     * This method is typically used as a resolver in a GraphQL context to handle
     * redirect tracking for Tapbuy. It processes the incoming request and returns
     * the appropriate redirect response.
     *
     * @param Field $field The GraphQL field being resolved.
     * @param ContextInterface $context The context of the GraphQL query.
     * @param ResolveInfo $info Metadata for the GraphQL query.
     * @param array|null $value The value passed from the parent resolver.
     * @param array|null $args The arguments provided in the GraphQL query.
     * @return array The result of the redirect tracking resolution.
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $input = $args['input'] ?? [];
        
        // Step 1: Validate and normalize input
        $normalizedInput = $this->validateInput($input);
        
        // Step 2: Set cookies in the helper
        $this->helper->setCookies($normalizedInput['cookies']);
        
        // Step 3: Resolve and authorize cart
        $quote = $this->resolveAndAuthorizeCart($normalizedInput['cart_id'], $context);
        
        // Step 4: Check preconditions
        if (!$this->checkPreconditions($quote)) {
            return $this->buildErrorResponse('Cart not found.');
        }
        
        if (!$this->config->isEnabled()) {
            return $this->buildErrorResponse('Tapbuy is disabled.', '/checkout');
        }
        
        // Step 5: Execute business logic and generate response
        return $this->executeBusinessLogicAndBuildResponse(
            $quote,
            $normalizedInput,
            $input['cart_id']
        );
    }

    /**
     * Validates and normalizes the input for the resolver.
     *
     * @param array $input The raw GraphQL input
     * @return array The normalized input array
     * @throws \Magento\Framework\Exception\LocalizedException If validation fails
     */
    private function validateInput(array $input): array
    {
        return $this->inputValidator->validate($input);
    }

    /**
     * Resolves the cart and validates ownership.
     *
     * @param string|int $cartId The cart ID (masked or numeric)
     * @param ContextInterface $context The GraphQL context
     * @return Quote The resolved quote
     * @throws \Magento\Framework\Exception\LocalizedException If authorization fails
     */
    private function resolveAndAuthorizeCart($cartId, ContextInterface $context): Quote
    {
        $quote = $this->cartResolver->resolveAndLoadQuote($cartId);
        $customerId = $context->getUserId();
        $this->cartOwnershipValidator->validateOwnership($quote, $customerId);
        return $quote;
    }

    /**
     * Checks if preconditions are met for proceeding.
     *
     * @param Quote $quote The quote to check
     * @return bool True if preconditions are met, false otherwise
     */
    private function checkPreconditions(Quote $quote): bool
    {
        return $quote && $quote->getId();
    }

    /**
     * Executes business logic (AB test) and builds the response.
     *
     * @param Quote $quote The cart quote
     * @param array $normalizedInput The normalized input with optional parameters
     * @param string|int $originalCartId The original cart ID for pixel generation
     * @return array The redirect response array
     */
    private function executeBusinessLogicAndBuildResponse(
        Quote $quote,
        array $normalizedInput,
        $originalCartId
    ): array {
        // Execute AB test
        $result = $this->service->triggerABTest(
            $quote,
            $normalizedInput['force_redirect'],
            $normalizedInput['referer']
        );

        // Generate pixel URL for headless frontend
        $pixelData = $this->helper->generatePixelData($originalCartId, $result, 'redirect_check');
        $pixelUrl = $this->helper->generatePixelUrl($pixelData);

        // Build and return redirect response
        return $this->buildRedirectResponse($result, $pixelUrl);
    }

    /**
     * Builds the redirect response based on AB test result.
     *
     * @param array $result The AB test result
     * @param string|null $pixelUrl The pixel tracking URL
     * @return array The response array with redirect decision and pixel URL
     */
    private function buildRedirectResponse(array $result, ?string $pixelUrl): array
    {
        if ($result && isset($result['redirect']) && $result['redirect'] === true && isset($result['redirectURL'])) {
            return [
                'redirect' => true,
                'redirect_url' => $result['redirectURL'],
                'message' => 'Redirect to Tapbuy.',
                'pixel_url' => $pixelUrl
            ];
        }

        return [
            'redirect' => false,
            'redirect_url' => '/checkout',
            'message' => 'Redirect to standard checkout.',
            'pixel_url' => $pixelUrl
        ];
    }

    /**
     * Builds an error response for early-exit conditions.
     *
     * @param string $message The error message
     * @param string|null $redirectUrl The fallback redirect URL
     * @return array The error response array
     */
    private function buildErrorResponse(string $message, ?string $redirectUrl = null): array
    {
        return [
            'redirect' => false,
            'redirect_url' => $redirectUrl,
            'message' => $message,
            'pixel_url' => null
        ];
    }
}
