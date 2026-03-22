<?php

declare(strict_types=1);

namespace Tapbuy\RedirectTracking\Model\Resolver;

use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Tapbuy\RedirectTracking\Api\ABTestInterface;
use Tapbuy\RedirectTracking\Api\ConfigInterface;
use Tapbuy\RedirectTracking\Api\LoggerInterface;
use Tapbuy\RedirectTracking\Api\Order\OrderLocatorInterface;
use Tapbuy\RedirectTracking\Api\TapbuyConstants;

class ConfirmOrder implements ResolverInterface
{
    private const TRACKING_FLAG = TapbuyConstants::ABTEST_TRACKING_FLAG;

    /**
     * @param OrderLocatorInterface $orderLocator
     * @param ABTestInterface $abTest
     * @param ConfigInterface $config
     * @param LoggerInterface $logger
     * @param OrderPaymentRepositoryInterface $paymentRepository
     */
    public function __construct(
        private readonly OrderLocatorInterface $orderLocator,
        private readonly ABTestInterface $abTest,
        private readonly ConfigInterface $config,
        private readonly LoggerInterface $logger,
        private readonly OrderPaymentRepositoryInterface $paymentRepository
    ) {
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
        ?array $value = null,
        ?array $args = null
    ) {
        if (!$this->config->isEnabled()) {
            // Tapbuy disabled: treat as successful no-op to avoid signaling an error to clients
            return true;
        }

        $input = $args['input'] ?? [];
        return $this->processOrderConfirmation(
            $input['order_number'] ?? null,
            $input['ab_test_id'] ?? null
        );
    }

    /**
     * Validate inputs, locate the order, and record the A/B test transaction.
     *
     * @param string|null $orderNumber
     * @param string|null $abTestId
     * @return bool
     * @throws GraphQlInputException
     */
    private function processOrderConfirmation(?string $orderNumber, ?string $abTestId): bool
    {
        try {
            if (!$orderNumber || !$abTestId) {
                $this->logger->warning('ConfirmOrder: Missing required input fields', [
                    'order_number' => $orderNumber,
                    'ab_test_id'   => $abTestId,
                ]);
                throw new GraphQlInputException(__('Both order_number and ab_test_id are required.'));
            }

            $order = $this->fetchOrder($orderNumber, $abTestId);
            if ($order === null) {
                return true;
            }

            $this->trackOrder($order, $abTestId, $orderNumber);
            return true;
        } catch (CouldNotSaveException $e) {
            $this->logger->logException('ConfirmOrder: Failed to save payment tracking flag', $e, [
                'order_number' => $orderNumber,
                'ab_test_id'   => $abTestId,
            ]);
            return true;
        } catch (\RuntimeException $e) {
            $this->logger->logException('ConfirmOrder: Error processing order confirmation', $e, [
                'order_number' => $orderNumber,
                'ab_test_id'   => $abTestId,
            ]);
            return true;
        }
    }

    /**
     * Locate the order by increment ID, returning null (and logging a warning) when not found.
     *
     * @param string $orderNumber
     * @param string $abTestId
     * @return OrderInterface|null
     */
    private function fetchOrder(string $orderNumber, string $abTestId): ?OrderInterface
    {
        try {
            return $this->orderLocator->getByIdentifier(
                $orderNumber,
                OrderLocatorInterface::IDENTIFIER_TYPE_INCREMENT_ID
            );
        } catch (NoSuchEntityException $e) {
            $this->logger->warning('ConfirmOrder: Order not found', [
                'order_number' => $orderNumber,
                'ab_test_id'   => $abTestId,
            ]);
            return null;
        }
    }

    /**
     * Run idempotency check, record the A/B test transaction, and persist the tracking flag.
     *
     * @param OrderInterface $order
     * @param string $abTestId
     * @param string $orderNumber
     * @return void
     */
    private function trackOrder(OrderInterface $order, string $abTestId, string $orderNumber): void
    {
        $payment = $order->getPayment();

        // Idempotency check: if already tracked, return silently
        if ($payment && $payment->getAdditionalInformation(self::TRACKING_FLAG)) {
            $this->logger->debug('ConfirmOrder: Order already tracked, skipping', [
                'order_number' => $orderNumber,
            ]);
            return;
        }

        $tracked = $this->abTest->processOrderTransaction($order, $abTestId);
        if ($tracked) {
            $this->logger->info('ConfirmOrder: Order successfully tracked', [
                'order_number' => $orderNumber,
                'ab_test_id'   => $abTestId,
            ]);
        }

        // Set the tracking flag to prevent duplicate processing
        if ($payment) {
            $payment->setAdditionalInformation(self::TRACKING_FLAG, true);
            $this->paymentRepository->save($payment);
        }
    }
}
