<?php

declare(strict_types=1);

namespace Tapbuy\RedirectTracking\Test\Unit\Model\Order;

use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\RedirectTracking\Api\Order\OrderLocatorInterface;
use Tapbuy\RedirectTracking\Model\Order\OrderLocator;

class OrderLocatorTest extends TestCase
{
    private OrderLocator $orderLocator;
    private OrderRepositoryInterface&MockObject $orderRepository;
    private SearchCriteriaBuilderFactory&MockObject $searchCriteriaBuilderFactory;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->searchCriteriaBuilderFactory = $this->createMock(SearchCriteriaBuilderFactory::class);

        $this->orderLocator = new OrderLocator(
            $this->orderRepository,
            $this->searchCriteriaBuilderFactory
        );
    }

    public function testGetByIdentifierThrowsOnEmptyIdentifier(): void
    {
        $this->expectException(NoSuchEntityException::class);
        $this->orderLocator->getByIdentifier('   ');
    }

    public function testGetByEntityIdReturnsOrder(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $this->orderRepository->method('get')->with(42)->willReturn($order);

        $result = $this->orderLocator->getByIdentifier('42', OrderLocatorInterface::IDENTIFIER_TYPE_ENTITY_ID);
        $this->assertSame($order, $result);
    }

    public function testGetByEntityIdThrowsForNonNumericId(): void
    {
        $this->expectException(NoSuchEntityException::class);
        $this->orderLocator->getByIdentifier('abc', OrderLocatorInterface::IDENTIFIER_TYPE_ENTITY_ID);
    }

    public function testGetByIncrementIdReturnsOrder(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(1);

        $searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $searchCriteriaBuilder->method('addFilter')->willReturnSelf();
        $searchCriteriaBuilder->method('setPageSize')->willReturnSelf();
        $searchCriteria = $this->createMock(SearchCriteria::class);
        $searchCriteriaBuilder->method('create')->willReturn($searchCriteria);

        $searchResults = $this->createMock(SearchResultsInterface::class);
        $searchResults->method('getItems')->willReturn([$order]);

        $this->searchCriteriaBuilderFactory->method('create')->willReturn($searchCriteriaBuilder);
        $this->orderRepository->method('getList')->willReturn($searchResults);

        $result = $this->orderLocator->getByIdentifier(
            '100000001',
            OrderLocatorInterface::IDENTIFIER_TYPE_INCREMENT_ID
        );
        $this->assertSame($order, $result);
    }

    public function testAutoModeTriesIncrementIdThenEntityId(): void
    {
        // increment_id search returns no results
        $searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $searchCriteriaBuilder->method('addFilter')->willReturnSelf();
        $searchCriteriaBuilder->method('setPageSize')->willReturnSelf();
        $searchCriteria = $this->createMock(SearchCriteria::class);
        $searchCriteriaBuilder->method('create')->willReturn($searchCriteria);

        $searchResults = $this->createMock(SearchResultsInterface::class);
        $searchResults->method('getItems')->willReturn([]);

        $this->searchCriteriaBuilderFactory->method('create')->willReturn($searchCriteriaBuilder);
        $this->orderRepository->method('getList')->willReturn($searchResults);

        // entity_id lookup succeeds
        $order = $this->createMock(OrderInterface::class);
        $this->orderRepository->method('get')->with(42)->willReturn($order);

        $result = $this->orderLocator->getByIdentifier('42');
        $this->assertSame($order, $result);
    }

    public function testAutoModeThrowsWhenBothFail(): void
    {
        $searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $searchCriteriaBuilder->method('addFilter')->willReturnSelf();
        $searchCriteriaBuilder->method('setPageSize')->willReturnSelf();
        $searchCriteria = $this->createMock(SearchCriteria::class);
        $searchCriteriaBuilder->method('create')->willReturn($searchCriteria);

        $searchResults = $this->createMock(SearchResultsInterface::class);
        $searchResults->method('getItems')->willReturn([]);

        $this->searchCriteriaBuilderFactory->method('create')->willReturn($searchCriteriaBuilder);
        $this->orderRepository->method('getList')->willReturn($searchResults);
        $this->orderRepository->method('get')->willThrowException(
            new NoSuchEntityException(__('Not found'))
        );

        $this->expectException(NoSuchEntityException::class);
        $this->orderLocator->getByIdentifier('nonexistent');
    }
}
