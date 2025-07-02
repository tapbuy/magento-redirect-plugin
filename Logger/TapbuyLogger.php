<?php
/**
 * Tapbuy Checkout Logger
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Logger;

use Monolog\Logger;

class TapbuyLogger extends Logger
{
    /**
     * TapbuyLogger constructor.
     *
     * @param string $name
     * @param array $handlers
     * @param array $processors
     */
    public function __construct(
        $name = 'tapbuy',
        array $handlers = [],
        array $processors = []
    ) {
        parent::__construct($name, $handlers, $processors);
    }
}