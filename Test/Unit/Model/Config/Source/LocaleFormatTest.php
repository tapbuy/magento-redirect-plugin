<?php

declare(strict_types=1);

namespace Tapbuy\RedirectTracking\Test\Unit\Model\Config\Source;

use PHPUnit\Framework\TestCase;
use Tapbuy\RedirectTracking\Model\Config\Source\LocaleFormat;

class LocaleFormatTest extends TestCase
{
    public function testToOptionArrayReturnsTwoOptions(): void
    {
        $source = new LocaleFormat();
        $options = $source->toOptionArray();

        $this->assertCount(2, $options);
    }

    public function testOptionsContainLongAndShort(): void
    {
        $source = new LocaleFormat();
        $options = $source->toOptionArray();
        $values = array_column($options, 'value');

        $this->assertContains('long', $values);
        $this->assertContains('short', $values);
    }
}
