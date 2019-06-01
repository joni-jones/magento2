<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Sales\Model\Order;

use Magento\AsynchronousOperations\Api\Data\OperationInterface;
use Magento\Framework\EntityManager\EntityManager;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

class PaymentPlaceConsumer
{
    /**
     * @var Payment
     */
    private $payment;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var EntityManager
     */
    private $entityManager;

    public function __construct(
        SerializerInterface $serializer,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger,
        EntityManager $entityManager,
        Payment $payment
    ) {
        $this->payment = $payment;
        $this->serializer = $serializer;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
        $this->entityManager = $entityManager;
    }

    public function process(OperationInterface $operation): void
    {
        $status = OperationInterface::STATUS_TYPE_COMPLETE;
        $data = $this->serializer->unserialize($operation->getSerializedData());
        $orderId = (int)$data['order_id'];

        try {
            $order = $this->orderRepository->get($orderId);
            $this->payment->setOrder($order);
            $this->payment->place();
        } catch (InputException | NoSuchEntityException $e) {
            list($errorCode, $message, $status) = [$e->getCode(), $e->getMessage(), OperationInterface::STATUS_TYPE_RETRIABLY_FAILED];
            $this->logger->error('Order entity cannot be found id: ' . $orderId , [$e]);
        } catch (\Exception $e) {
            list($errorCode, $message, $status) = [$e->getCode(), $e->getMessage(), OperationInterface::STATUS_TYPE_NOT_RETRIABLY_FAILED];
            $this->logger->error('Payment transaction cannot be handled', [$e]);
        } finally {
            $operation->setStatus($status)
                ->setResultMessage($message ?? null)
                ->setErrorCode($errorCode ?? null);
            $this->entityManager->save($operation);
        }
    }
}
