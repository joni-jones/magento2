<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Sales\Model\Order;

use Magento\AsynchronousOperations\Api\Data\OperationInterface;
use Magento\AsynchronousOperations\Api\Data\OperationInterfaceFactory;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Framework\DataObject\IdentityGeneratorInterface;
use Magento\Framework\Serialize\SerializerInterface;

class PaymentPlacePublisher
{
    /**
     * @var PublisherInterface
     */
    private $publisher;
    /**
     * @var OperationInterfaceFactory
     */
    private $operationFactory;
    /**
     * @var IdentityGeneratorInterface
     */
    private $identityGenerator;
    /**
     * @var SerializerInterface
     */
    private $serializer;

    public function __construct(
        PublisherInterface $publisher,
        IdentityGeneratorInterface $identityGenerator,
        SerializerInterface $serializer,
        OperationInterfaceFactory $operationFactory
    )
    {
        $this->publisher = $publisher;
        $this->operationFactory = $operationFactory;
        $this->identityGenerator = $identityGenerator;
        $this->serializer = $serializer;
    }

    public function publish(OrderInterface $order): void
    {
        $topic = 'sales_order.place';
        $uid = $this->identityGenerator->generateId();
        $data = $this->serializer->serialize(['order_id' => (int)$order->getEntityId()]);
        $operation = $this->operationFactory->create([
            'data' => [
                'bulk_uuid' => $uid,
                'topic_name' => $topic,
                'serialized_data' => $data,
                'status' => OperationInterface::STATUS_TYPE_OPEN,
            ]
        ]);
        $this->publisher->publish($topic, $operation);
    }
}
