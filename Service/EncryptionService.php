<?php

declare(strict_types=1);

/**
 * Tapbuy Encryption Service
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Service;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\Data\CartInterface;
use Tapbuy\RedirectTracking\Api\Cart\CartResolverInterface;
use Tapbuy\RedirectTracking\Api\ConfigInterface;

class EncryptionService
{
    /**
     * @param ConfigInterface $config
     * @param RequestInterface $request
     * @param Json $json
     * @param CartResolverInterface $cartResolver
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly RequestInterface $request,
        private readonly Json $json,
        private readonly CartResolverInterface $cartResolver
    ) {
    }

    /**
     * Get encrypted key for Tapbuy.
     *
     * Serializes session and cart data to JSON, then encrypts it with AES-256-GCM
     * using the configured encryption key. Returns an empty string when the key
     * is not configured.
     *
     * @param CartInterface|null $quote Active quote, or null when unavailable.
     * @return string Base64-encoded encrypted payload, or empty string when no encryption key is configured.
     * @throws \Exception If encryption fails.
     */
    public function getTapbuyKey(?CartInterface $quote): string
    {
        $data = $this->buildEncryptionData($quote);

        // Convert to JSON and encrypt
        $jsonData = $this->json->serialize($data);
        $encryptionKey = $this->config->getEncryptionKey();

        if (!$encryptionKey) {
            return '';
        }

        return $this->encryptData($jsonData, $encryptionKey);
    }

    /**
     * Build encryption data from request and quote.
     *
     * Extracts the Bearer token from the Authorization header and the masked cart ID
     * from the quote. Only the keys that are available at call-time are included.
     *
     * @param CartInterface|null $quote Active quote, or null when unavailable.
     * @return array{
     *   session_id?: string,
     *   cart_id?: string
     * }
     */
    private function buildEncryptionData(?CartInterface $quote): array
    {
        $data = [];

        // Get Authorization header from request
        $authorizationHeader = $this->request->getHeader('Authorization');
        if ($authorizationHeader) {
            // Extract Bearer token
            if (preg_match('/Bearer\s(\S+)/', $authorizationHeader, $matches)) {
                $data['session_id'] = $matches[1];
            }
        }

        // Get cart data if available
        if ($quote && $quote->getId()) {
            // Guest cart, retrieve masked ID via centralized CartResolver
            $data['cart_id'] = $this->cartResolver->getMaskedCartId((int) $quote->getId());
        }

        return $data;
    }

    /**
     * Encrypt data using AES-256-GCM
     *
     * @param string $jsonData
     * @param string $encryptionKey
     * @return string
     */
    private function encryptData(string $jsonData, string $encryptionKey): string
    {
        // Ensure key is 32 bytes for AES-256
        $encryptionKey = substr(str_pad($encryptionKey, 32, "\0"), 0, 32);
        $initVector = random_bytes(12); // 12-byte nonce for GCM
        $tag = '';
        $cipherText = openssl_encrypt($jsonData, 'aes-256-gcm', $encryptionKey, OPENSSL_RAW_DATA, $initVector, $tag);

        if ($cipherText === false) {
            return '';
        }

        return base64_encode($initVector . $tag . $cipherText);
    }
}
