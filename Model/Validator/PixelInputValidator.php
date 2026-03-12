<?php

declare(strict_types=1);

/**
 * Pixel Input Validator
 *
 * Validates, sanitizes and size-limits input received by the pixel tracking
 * endpoint. Prevents log-injection attacks, oversized payloads, and unknown
 * field leakage into the application.
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Model\Validator;

use Tapbuy\RedirectTracking\Helper\JsonDecodeHelper;

class PixelInputValidator
{
    /**
     * Maximum byte length of the raw (base64-encoded) data query parameter.
     * Checked before any decoding work to prevent decode-bomb payloads.
     * The five valid pixel fields fit comfortably within ~300 bytes of JSON,
     * which base64-encodes to ~400 bytes — 2 KB gives ample legitimate headroom.
     */
    private const MAX_RAW_INPUT_BYTES = 2048;

    /**
     * Maximum length for any individual string field value after sanitization.
     */
    private const MAX_STRING_VALUE_LENGTH = 255;

    /**
     * Allowed fields and their expected PHP types.
     * Keys not present in this map are silently dropped.
     *
     * @var array<string, string>
     */
    private const ALLOWED_FIELDS = [
        'action'             => 'string',
        'cart_id'            => 'string',
        'test_id'            => 'string',
        'timestamp'          => 'integer',
        'remove_test_cookie' => 'boolean',
    ];

    /**
     * Characters stripped from string values to prevent log injection.
     * Newlines (\n \r) and null bytes (\0) are the primary log-forging vectors.
     */
    private const LOG_INJECTION_CHARS = ["\n", "\r", "\0"];

    /**
     * @param JsonDecodeHelper $jsonDecodeHelper
     */
    public function __construct(
        private readonly JsonDecodeHelper $jsonDecodeHelper
    ) {
    }

    /**
     * Check whether the raw (base64) input is within the allowed size limit.
     *
     * Call this before any decode operation so oversized payloads never reach
     * base64_decode / json_decode.
     *
     * @param string $input Raw base64-encoded query parameter value
     * @return bool
     */
    public function isInputSizeValid(string $input): bool
    {
        return strlen($input) <= self::MAX_RAW_INPUT_BYTES;
    }

    /**
     * Decode base64+JSON pixel data and sanitize the resulting array.
     *
     * Returns only the fields declared in ALLOWED_FIELDS, coerced to their
     * declared types. Unknown keys are silently dropped. String values are
     * stripped of log-injection characters and truncated.
     *
     * @param string $encodedData Raw base64-encoded query parameter value
     * @return array<string, mixed>
     */
    public function decodeAndSanitize(string $encodedData): array
    {
        $decoded = $this->jsonDecodeHelper->decodeToArray($encodedData, true);
        return $this->sanitizeData($decoded);
    }

    /**
     * Sanitize a cookie value received as a query parameter.
     *
     * Strips log-injection characters (\n, \r, \0) while preserving characters
     * that are valid in real cookie values (dots, equals signs, hyphens, etc.).
     * Truncates to MAX_STRING_VALUE_LENGTH to limit log bloat.
     *
     * @param string $value Raw cookie value from query parameter
     * @return string
     */
    public function sanitizeCookieValue(string $value): string
    {
        return $this->sanitizeString($value);
    }

    /**
     * Filter and coerce a decoded data array against ALLOWED_FIELDS.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function sanitizeData(array $data): array
    {
        $sanitized = [];

        foreach (self::ALLOWED_FIELDS as $field => $expectedType) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];

            $coerced = $this->coerceValue($value, $expectedType);
            if ($coerced === null) {
                // Value could not be coerced to the declared type — drop it
                continue;
            }

            $sanitized[$field] = $coerced;
        }

        return $sanitized;
    }

    /**
     * Coerce a value to the expected PHP type, returning null if not possible.
     *
     * @param mixed  $value
     * @param string $expectedType 'string' | 'integer' | 'boolean'
     * @return mixed|null
     */
    private function coerceValue(mixed $value, string $expectedType): mixed
    {
        switch ($expectedType) {
            case 'string':
                if (!is_scalar($value)) {
                    return null;
                }
                return $this->sanitizeString((string) $value);

            case 'integer':
                if (!is_int($value) && !is_string($value)) {
                    return null;
                }
                if (is_string($value) && !ctype_digit($value)) {
                    return null;
                }
                return (int) $value;

            case 'boolean':
                if (!is_bool($value) && !in_array($value, [0, 1, '0', '1'], true)) {
                    return null;
                }
                return (bool) $value;

            default:
                return null;
        }
    }

    /**
     * Strip log-injection characters and truncate a string value.
     *
     * @param string $value
     * @return string
     */
    private function sanitizeString(string $value): string
    {
        $value = str_replace(self::LOG_INJECTION_CHARS, '', $value);
        return substr($value, 0, self::MAX_STRING_VALUE_LENGTH);
    }
}
