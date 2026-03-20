<?php

declare(strict_types=1);

namespace Tapbuy\RedirectTracking\Test\Unit\Model\Config\Source;

use PHPUnit\Framework\TestCase;
use Tapbuy\RedirectTracking\Model\Config\Source\OrderConfirmationMode;

class OrderConfirmationModeTest extends TestCase
{
    public function testToOptionArrayReturnsThreeOptions(): void
    {
        $source = new OrderConfirmationMode();
        $options = $source->toOptionArray();

        $this->assertCount(3, $options);
    }

    public function testOptionsContainRequiredValues(): void
    {
        $source = new OrderConfirmationMode();
        $options = $source->toOptionArray();
        $values = array_column($options, 'value');

        $this->assertContains('graphql', $values);
        $this->assertContains('observer', $values);
        $this->assertContains('both', $values);
    }
}
