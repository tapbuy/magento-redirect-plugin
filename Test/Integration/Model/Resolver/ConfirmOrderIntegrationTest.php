<?php

declare(strict_types=1);

namespace Tapbuy\RedirectTracking\Test\Integration\Model\Resolver;

use PHPUnit\Framework\TestCase;

/**
 * Integration test stub for ConfirmOrder GraphQL resolver.
 */
class ConfirmOrderIntegrationTest extends TestCase
{
    public function testConfirmOrderTracksNewOrder(): void
    {
        $this->markTestIncomplete(
            'Integration test requires Magento bootstrap, order fixtures, and Tapbuy API mock.'
        );
    }

    public function testConfirmOrderSkipsAlreadyTrackedOrder(): void
    {
        $this->markTestIncomplete(
            'Integration test requires order with tracking flag already set.'
        );
    }
}
