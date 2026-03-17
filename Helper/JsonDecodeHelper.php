<?php

declare(strict_types=1);

/**
 * JSON Decode Helper
 *
 * Centralises the repeated "decode-or-return-empty-array" pattern used when
 * accepting input that may arrive as an array, a JSON string, or a
 * base64-encoded JSON string.
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Helper;

class JsonDecodeHelper
{
    /**
     * Decode input to an array, handling multiple possible encodings.
     *
     * Supported input formats:
     * - array      — returned as-is
     * - JSON string (base64 = false) — decoded or empty array on failure
     * - base64-encoded JSON string (base64 = true) — both layers decoded, or empty array on failure
     * - any other type — empty array
     *
     * @param mixed $input  The value to decode
     * @param bool  $base64 Whether to base64-decode the string before JSON-decoding
     * @return array
     */
    public function decodeToArray(mixed $input, bool $base64 = false): array
    {
        if (is_array($input)) {
            return $input;
        }

        if (!is_string($input) || $input === '') {
            return [];
        }

        // phpcs:disable Magento2.Functions.DiscouragedFunction.Discouraged
        $string = $base64 ? base64_decode($input, true) : $input;
        // phpcs:enable Magento2.Functions.DiscouragedFunction.Discouraged
        if ($string === false) {
            return [];
        }

        $decoded = json_decode($string, true);
        return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
    }
}
