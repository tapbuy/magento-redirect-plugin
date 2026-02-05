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
use Magento\Quote\Model\QuoteIdMaskFactory;
use Tapbuy\RedirectTracking\Api\ConfigInterface;

class EncryptionService
{
    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * EncryptionService constructor.
     *
     * @param ConfigInterface $config
     * @param RequestInterface $request
     * @param Json $json
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     */
    public function __construct(
        ConfigInterface $config,
        RequestInterface $request,
        Json $json,
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        $this->config = $config;
        $this->request = $request;
        $this->json = $json;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
    }

    /**
     * Get encrypted key for Tapbuy
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return string
     */
    public function getTapbuyKey($quote): string
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
     * Build encryption data from request and quote
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return array
     */
    private function buildEncryptionData($quote): array
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
            // Guest cart, retrieve masked ID
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load($quote->getId(), 'quote_id');
            $data['cart_id'] = $quoteIdMask->getMaskedId();
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
