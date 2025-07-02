<?php
/**
 * Tapbuy Redirect and Tracking Install Data
 *
 * @category  Tapbuy
 * @package   Tapbuy_RedirectTracking
 */

namespace Tapbuy\RedirectTracking\Setup;

use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Store\Model\ScopeInterface;

class InstallData implements InstallDataInterface
{
    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * InstallData constructor.
     *
     * @param WriterInterface $configWriter
     */
    public function __construct(
        WriterInterface $configWriter
    ) {
        $this->configWriter = $configWriter;
    }

    /**
     * Install default configuration
     *
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        // Set default configuration values
        $this->configWriter->save('tapbuy/general/enabled', '0', ScopeInterface::SCOPE_STORES, 0);
        $this->configWriter->save('tapbuy/general/mobile_redirection_enabled', '1', ScopeInterface::SCOPE_STORES, 0);
        $this->configWriter->save('tapbuy/general/desktop_redirection_enabled', '1', ScopeInterface::SCOPE_STORES, 0);
        $this->configWriter->save('tapbuy/api/api_url', 'https://api.tapbuy.io', ScopeInterface::SCOPE_STORES, 0);
        $this->configWriter->save('tapbuy/gifting/enabled', '0', ScopeInterface::SCOPE_STORES, 0);

        $setup->endSetup();
    }
}