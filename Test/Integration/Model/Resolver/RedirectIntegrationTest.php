<?php

declare(strict_types=1);

namespace Tapbuy\RedirectTracking\Test\Integration\Model\Resolver;

use PHPUnit\Framework\TestCase;

/**
 * Integration test stub for Redirect GraphQL resolver.
 */
class RedirectIntegrationTest extends TestCase
{
    public function testRedirectReturnsUrlForEligibleCart(): void
    {
        $this->markTestIncomplete(
            'Integration test requires Magento bootstrap, quote fixtures, and Tapbuy API mock.'
        );
    }

    public function testRedirectDeniedForEmptyCart(): void
    {
        $this->markTestIncomplete(
            'Integration test requires Magento bootstrap and empty quote fixture.'
        );
    }
}
