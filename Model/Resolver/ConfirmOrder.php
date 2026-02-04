<?php

namespace Tapbuy\RedirectTracking\Model\Resolver;

use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Tapbuy\RedirectTracking\Api\ABTestInterface;
use Tapbuy\RedirectTracking\Api\LoggerInterface;
use Tapbuy\RedirectTracking\Api\TapbuyConstants;

class ConfirmOrder implements ResolverInterface
{
    private const TRACKING_FLAG = TapbuyConstants::ABTEST_TRACKING_FLAG;

    /**
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * @var ABTestInterface
     */
    private $abTest;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var OrderPaymentRepositoryInterface
     */
    private $paymentRepository;

    /**
     * ConfirmOrder constructor.
     *
     * @param OrderFactory $orderFactory
     * @param ABTestInterface $abTest
     * @param LoggerInterface $logger
     * @param OrderPaymentRepositoryInterface $paymentRepository
     */
    public function __construct(
        OrderFactory $orderFactory,
        ABTestInterface $abTest,
        LoggerInterface $logger,
        OrderPaymentRepositoryInterface $paymentRepository
    ) {
        $this->orderFactory = $orderFactory;
        $this->abTest = $abTest;
        $this->logger = $logger;
        $this->paymentRepository = $paymentRepository;
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
     * @throws GraphQlInputException
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

        try {
            if (!$orderNumber || !$abTestId) {
                throw new GraphQlInputException(__('Both order_number and ab_test_id are required.'));
            }

            $order = $this->orderFactory->create()->loadByIncrementId($orderNumber);

            if (!$order->getId()) {
                $this->logger->warning('ConfirmOrder: Order not found', [
                    'order_number' => $orderNumber,
                    'ab_test_id' => $abTestId,
                ]);
                return true;
            }

            $payment = $order->getPayment();

            // Idempotency check: if already tracked, return silently
            if ($payment && $payment->getAdditionalInformation(self::TRACKING_FLAG)) {
                $this->logger->debug('ConfirmOrder: Order already tracked, skipping', [
                    'order_number' => $orderNumber,
                ]);
                return true;
            }

            $this->abTest->processOrderTransaction($order, $abTestId);

            // Set the tracking flag to prevent duplicate processing
            if ($payment) {
                $payment->setAdditionalInformation(self::TRACKING_FLAG, true);
                $this->paymentRepository->save($payment);
            }

            return true;
        } catch (\Exception $e) {
            $this->logger->logException('ConfirmOrder: Error processing order confirmation', $e, [
                'order_number' => $orderNumber,
                'ab_test_id' => $abTestId,
            ]);
            return true;
        }
    }
}
