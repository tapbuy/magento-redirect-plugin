<?php

/**
 * Tapbuy Redirect and Tracking Logger Handler
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Logger;

use Magento\Framework\Logger\Handler\Base;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\HTTP\Client\Curl;
use Monolog\Logger;
use Tapbuy\RedirectTracking\Model\Config;

class Handler extends Base
{
    /**
     * @var int
     * Handle all levels from DEBUG upward so debug() is sent.
     * (Change back to Logger::INFO if you only want >= INFO.)
     */
    protected $loggerType = Logger::DEBUG;

    /**
     * @var string
     */
    protected $fileName = '/var/log/tapbuy.log';

    /** @var Curl */
    private $curl;

    /** @var Config */
    private $config;

    /** @var string */
    private $baseUrl;

    public function __construct(
        DriverInterface $filesystem,
        Curl $curl,
        Config $config,
        $filePath = null
    ) {
        $this->curl = $curl;
        $this->config = $config;
        // Remove trailing slash to avoid double slashes when appending /debug
        $this->baseUrl = rtrim((string)$this->config->getApiUrl(), '/');
        parent::__construct($filesystem, $filePath);
    }

    /**
     * Write record to file and POST to /debug
     * @param array $record
     */
    protected function write(array $record): void
    {
        parent::write($record);

        $payload = [
            'message' => (string)$record['message'],
            'level' => (int)$record['level'],
            'level_name' => (string)$record['level_name'],
            'params' => isset($record['context']['params']) ? (array)$record['context']['params'] : (array)$record['context'],
            'sentry' => ($record['context'] ?? [])['sentry'] ?? false
        ];

        try {
            $this->curl->addHeader('Content-Type', 'application/json');
            $this->curl->addHeader('Authorization', 'Bearer ' . $this->config->getEncryptionKey());
            $this->curl->post($this->baseUrl . '/debug', json_encode($payload));
        } catch (\Throwable $e) {
            // Silently ignore to not block logging
        }
    }
}
