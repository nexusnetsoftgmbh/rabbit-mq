<?php

namespace Spryker\Client\RabbitMq\Model\Consumer;

use Generated\Shared\Transfer\QueueReceiveMessageTransfer;
use Generated\Shared\Transfer\QueueSendMessageTransfer;
use Generated\Shared\Transfer\RabbitMqConsumerOptionTransfer;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class Consumer implements ConsumerInterface
{

    const CONSUMER_TAG = 'consumerTag';
    const NO_LOCAL = 'noLocal';
    const NO_ACK = 'noAck';
    const EXCLUSIVE = 'exclusive';
    const NOWAIT = 'nowait';

    const QUEUE_LOG_FILE = 'queue.log';
    const DEFAULT_CONSUMER_TIMEOUT_SECONDS = 1;
    const DEFAULT_PREFETCH_COUNT = 100;

    /**
     * @var \PhpAmqpLib\Channel\AMQPChannel
     */
    protected $channel;

    /**
     * @var array
     */
    protected $collectedMessages = [];

    /**
     * @param AMQPChannel $channel
     */
    public function __construct(AMQPChannel $channel)
    {
        $this->channel = $channel;
    }

    /**
     * @param string $queueName
     * @param int $chunkSize
     * @param array|null $options
     *
     * @return QueueReceiveMessageTransfer[]
     */
    public function receiveMessages($queueName, $chunkSize = 100, array $options = null)
    {
        /** @var RabbitMqConsumerOptionTransfer $rabbitMqOption */
        $rabbitMqOption = $options['rabbitMqConsumerOption'];
        $this->channel->basic_qos(null, $chunkSize, null);
        $this->channel->basic_consume(
            $queueName,
            $rabbitMqOption->getConsumerTag(),
            $rabbitMqOption->getNoLocal(),
            $rabbitMqOption->getNoAck(),
            $rabbitMqOption->getConsumerExclusive(),
            $rabbitMqOption->getNoWait(),
            [$this, 'collectQueueMessages']
        );

        try {
            $finished = false;
            while (count($this->channel->callbacks) && !$finished) {
                $this->channel->wait(null, false, self::DEFAULT_CONSUMER_TIMEOUT_SECONDS);
            }
        } catch (\Exception $e) {
            $finished = true;
        }

        return $this->collectedMessages;
    }

    /**
     * @param string $queueName
     * @param array|null $options
     *
     * @return QueueReceiveMessageTransfer
     */
    public function receiveMessage($queueName, array $options = null)
    {
        /** @var RabbitMqConsumerOptionTransfer $rabbitMqOption */
        $rabbitMqOption = $options['rabbitMqConsumerOption'];

        $message = $this->channel->basic_get($queueName, $rabbitMqOption->getNoAck());

        $queueSendMessageTransfer = new QueueSendMessageTransfer();
        $queueSendMessageTransfer->setBody($message->getBody());

        $queueReceiveMessageTransfer = new QueueReceiveMessageTransfer();
        $queueReceiveMessageTransfer->setQueueMessage($queueSendMessageTransfer);
        $queueReceiveMessageTransfer->setQueueName($queueName);
        $queueReceiveMessageTransfer->setDeliveryTag($message->delivery_info['delivery_tag']);

        return $queueReceiveMessageTransfer;
    }

    /**
     * @param AMQPMessage $message
     *
     * @return void
     */
    public function collectQueueMessages(AMQPMessage $message)
    {

        $queueSendMessageTransfer = new QueueSendMessageTransfer();
        $queueSendMessageTransfer->setBody($message->getBody());

        $queueReceiveMessageTransfer = new QueueReceiveMessageTransfer();
        $queueReceiveMessageTransfer->setQueueMessage($queueSendMessageTransfer);
        // @TODO check this of queue not exchange!!!
        $queueReceiveMessageTransfer->setQueueName($message->delivery_info['exchange']);
        $queueReceiveMessageTransfer->setDeliveryTag($message->delivery_info['delivery_tag']);

        $this->collectedMessages[] = $queueReceiveMessageTransfer;
    }

    /**
     * @param QueueReceiveMessageTransfer $queueReceiveMessageTransfer
     *
     * @return bool
     */
    public function acknowledge(QueueReceiveMessageTransfer $queueReceiveMessageTransfer)
    {
        return $this->channel->basic_ack($queueReceiveMessageTransfer->getDeliveryTag());
    }

    /**
     * @param QueueReceiveMessageTransfer $queueReceiveMessageTransfer
     *
     * @return bool
     */
    public function reject(QueueReceiveMessageTransfer $queueReceiveMessageTransfer)
    {
        $this->channel->basic_reject($queueReceiveMessageTransfer->getDeliveryTag(), false);
    }

    /**
     * @param QueueReceiveMessageTransfer $queueReceiveMessageTransfer
     *
     * @return bool
     */
    public function handleError(QueueReceiveMessageTransfer $queueReceiveMessageTransfer)
    {
        $message = new AMQPMessage($queueReceiveMessageTransfer->getQueueMessage()->getBody());
        $this->channel->basic_publish($message, $queueReceiveMessageTransfer->getQueueName(), 'error');

        return true;
    }
}
