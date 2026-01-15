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
use Monolog\LogRecord;
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
     * Compatible with both Monolog 2.x (array) and Monolog 3.x (LogRecord)
     * @param array|LogRecord $record
     */
    protected function write(array|LogRecord $record): void
    {
        parent::write($record);

        // Normalize record for both Monolog 2.x (array) and 3.x (LogRecord)
        if ($record instanceof LogRecord) {
            $message = $record->message;
            $level = $record->level->value;
            $levelName = $record->level->name;
            $context = $record->context;
        } else {
            $message = $record['message'];
            $level = $record['level'];
            $levelName = $record['level_name'];
            $context = $record['context'] ?? [];
        }

        $payload = [
            'message' => (string)$message,
            'level' => (int)$level,
            'level_name' => (string)$levelName,
            'params' => isset($context['params']) ? (array)$context['params'] : (array)$context,
            'sentry' => $context['sentry'] ?? false
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
