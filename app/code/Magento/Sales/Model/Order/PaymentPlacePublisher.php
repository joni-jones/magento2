<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Sales\Model\Order;

use Magento\AsynchronousOperations\Api\Data\OperationInterface;
use Magento\AsynchronousOperations\Api\Data\OperationInterfaceFactory;
use Magento\Framework\Bulk\BulkManagementInterface;
use Magento\Framework\DataObject\IdentityGeneratorInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Api\Data\OrderInterface;

class PaymentPlacePublisher
{
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

    /**
     * @var BulkManagementInterface
     */
    private $bulkManagement;

    public function __construct(
        IdentityGeneratorInterface $identityGenerator,
        SerializerInterface $serializer,
        OperationInterfaceFactory $operationFactory,
        BulkManagementInterface $bulkManagement
    ) {
        $this->operationFactory = $operationFactory;
        $this->identityGenerator = $identityGenerator;
        $this->serializer = $serializer;
        $this->bulkManagement = $bulkManagement;
    }

    public function publish(OrderInterface $order): void
    {
        $topic = 'sales_order.place';
        $uid = $this->identityGenerator->generateId();
        $dateTime = new \DateTime();
        $data = $this->serializer->serialize(
            [
                'order_id' => (int)$order->getEntityId(),
                'timestamp' => $dateTime->format('Y-m-d H:i:s')
            ]
        );
        $operation = $this->operationFactory->create([
            'data' => [
                'bulk_uuid' => $uid,
                'topic_name' => $topic,
                'serialized_data' => $data,
                'status' => OperationInterface::STATUS_TYPE_OPEN,
            ]
        ]);
        $this->bulkManagement->scheduleBulk($uid, [$operation], 'Place order');
    }
}
