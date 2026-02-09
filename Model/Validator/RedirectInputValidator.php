<?php

declare(strict_types=1);

/**
 * Redirect Input Validator
 *
 * Validates and normalizes GraphQL input for the Redirect resolver.
 * Handles cart ID validation, cookies parsing, and optional parameter extraction.
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Model\Validator;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

class RedirectInputValidator
{
    /**
     * Validates and normalizes the input for the Redirect resolver.
     *
     * @param array $input The raw GraphQL input array
     * @return array The normalized input array with keys: cart_id, cookies, force_redirect, referer
     * @throws LocalizedException If validation fails
     */
    public function validate(array $input): array
    {
        $this->validateCartId($input);
        
        return [
            'cart_id' => $input['cart_id'],
            'cookies' => $this->parseCookies($input),
            'force_redirect' => $this->extractOptionalParameter($input, 'force_redirect'),
            'referer' => $this->extractOptionalParameter($input, 'referer'),
        ];
    }

    /**
     * Validates that cart ID is present and non-empty.
     *
     * @param array $input The raw input array
     * @return void
     * @throws LocalizedException If cart ID is missing or empty
     */
    private function validateCartId(array $input): void
    {
        if (empty($input['cart_id'])) {
            throw new LocalizedException(new Phrase('Cart ID is required.'));
        }
    }

    /**
     * Parses and normalizes cookies from input.
     *
     * Handles three formats:
     * 1. JSON string: Decodes JSON to array
     * 2. Array: Returns as-is
     * 3. Missing: Returns empty array
     *
     * @param array $input The raw input array
     * @return array The normalized cookies array
     */
    private function parseCookies(array $input): array
    {
        if (!isset($input['cookies'])) {
            return [];
        }

        $cookies = $input['cookies'];

        // Handle JSON string format
        if (is_string($cookies)) {
            $decoded = json_decode($cookies, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
            return [];
        }

        // Handle array format
        if (is_array($cookies)) {
            return $cookies;
        }

        return [];
    }

    /**
     * Extracts an optional parameter from input, defaulting to null if not present.
     *
     * @param array $input The raw input array
     * @param string $key The parameter key to extract
     * @return mixed The parameter value or null if not present
     */
    private function extractOptionalParameter(array $input, string $key)
    {
        return $input[$key] ?? null;
    }
}
