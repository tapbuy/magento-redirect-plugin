<?php

namespace Tapbuy\RedirectTracking\Model\Resolver;

use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Tapbuy\RedirectTracking\Model\ABTest;

class ConfirmOrder implements ResolverInterface
{
    /**
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * @var \Tapbuy\RedirectTracking\Model\ABTest
     */
    private $abTest;

    /**
     * ConfirmOrder constructor.
     *
     * @param OrderFactory $orderFactory
     * @param \Tapbuy\RedirectTracking\Model\ABTest $abTest
     */
    public function __construct(
        OrderFactory $orderFactory,
        \Tapbuy\RedirectTracking\Model\ABTest $abTest
    ) {
        $this->orderFactory = $orderFactory;
        $this->abTest = $abTest;
    }

    /**
     * Resolves the confirm order mutation.
     *
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return bool
     * @throws LocalizedException
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $input = $args['input'] ?? [];
        $orderNumber = $input['order_number'] ?? null;
        $abTestId = $input['ab_test_id'] ?? null;

        if (!$orderNumber || !$abTestId) {
            throw new LocalizedException(new Phrase('Both order_number and ab_test_id are required.'));
        }

        $order = $this->orderFactory->create()->loadByIncrementId($orderNumber);

        if (!$order->getId()) {
            throw new LocalizedException(new Phrase('Order not found.'));
        }

        $this->abTest->processOrderTransaction($order, $abTestId);

        return true;
    }
}
