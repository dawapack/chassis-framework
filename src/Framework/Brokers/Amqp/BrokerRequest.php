<?php

declare(strict_types=1);

namespace Chassis\Framework\Brokers\Amqp;

use Chassis\Framework\Brokers\Amqp\MessageBags\AbstractMessageBag;
use Chassis\Framework\Brokers\Amqp\MessageBags\MessageBagInterface;
use Chassis\Framework\Brokers\Amqp\MessageBags\RequestMessageBagInterface;

class BrokerRequest extends AbstractMessageBag implements RequestMessageBagInterface
{
    /**
     * @inheritdoc
     */
    public function fromContext(MessageBagInterface $messageBag, string $operation): BrokerRequest
    {
        // copy & adapt context properties to response
        $this->setMessageType($operation);
        if (isset($messageBag->properties->application_headers["jobId"])) {
            $this->setHeader("jobId", $messageBag->properties->application_headers["jobId"]);
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setMessageType(string $messageType): BrokerRequest
    {
        $this->properties->type = $messageType;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setReplyTo(string $replyTo): BrokerRequest
    {
        $this->properties->reply_to = $replyTo;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setChannelName(string $channelName): BrokerRequest
    {
        $this->bindings->channelName = $channelName;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setRoutingKey(string $routingKey): BrokerRequest
    {
        $this->bindings->routingKey = $routingKey;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setExchangeName(string $exchangeName): BrokerRequest
    {
        $this->bindings->exchange = $exchangeName;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setQueueName(string $queueName): BrokerRequest
    {
        $this->bindings->queue = $queueName;
        return $this;
    }

    /**
     * @param string $routingKey
     * @param string $channelName
     *
     * @return BrokerResponse
     * @throws \Chassis\Framework\Brokers\Exceptions\MessageBagFormatException
     * @throws \JsonException
     */
    public function send(string $routingKey, string $channelName): BrokerResponse
    {
        // TODO: implement send mechanism - use RemoteProcedureCallStreamer::class
        return new BrokerResponse([]);
    }
}
