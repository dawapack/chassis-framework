<?php

declare(strict_types=1);

namespace Chassis\Helpers;

use Chassis\Framework\Brokers\Amqp\MessageBags\MessageBagInterface;
use Chassis\Framework\Brokers\Amqp\Streamers\PublisherStreamer;
use Chassis\Framework\Brokers\Exceptions\BrokerOperationTimeoutException;
use Closure;
use Chassis\Framework\Brokers\Amqp\BrokerRequest;
use Chassis\Framework\Brokers\Amqp\BrokerResponse;
use Chassis\Framework\Brokers\Amqp\Handlers\MessageHandlerInterface;
use Chassis\Framework\Brokers\Amqp\Streamers\PublisherStreamerInterface;
use Chassis\Framework\Brokers\Amqp\Streamers\SubscriberStreamer;
use Chassis\Framework\Brokers\Amqp\Streamers\SubscriberStreamerInterface;
use PhpAmqpLib\Message\AMQPMessage;

if (!function_exists('publish')) {
    /**
     * @param MessageBagInterface $messageBag
     * @param string|null $channelName
     *
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    function publish(
        MessageBagInterface $messageBag,
        string $channelName = "",
        int $publishAcknowledgeTimeout = 5
    ): void {
        /** @var PublisherStreamer $publisher */
        $publisher = app(PublisherStreamerInterface::class);
        $publisher->publish($messageBag, $channelName, $publishAcknowledgeTimeout);
    }
}

if (!function_exists('subscribe')) {
    /**
     * @param string $channelName
     * @param string $messageBagHandler - BrokerRequest::class or BrokerResponse::class
     * @param Closure|MessageHandlerInterface|null $messageHandler
     *
     * @return SubscriberStreamer
     */
    function subscribe(
        string $channelName,
        string $messageBagHandler,
        $messageHandler = null
    ): SubscriberStreamer {
        /** @var SubscriberStreamer $subscriber */
        $subscriber = app(SubscriberStreamerInterface::class);
        return $subscriber->setChannelName($channelName)
            ->setHandler($messageBagHandler)
            ->consume($messageHandler);
    }
}

if (!function_exists('remoteProcedureCall')) {
    /**
     * @param BrokerRequest $message
     * @param int $timeout
     *
     * @return BrokerResponse|null
     * @throws \Chassis\Framework\Brokers\Exceptions\MessageBagFormatException
     * @throws \JsonException
     */
    function remoteProcedureCall(
        BrokerRequest $message,
        int $timeout = 30
    ): ?BrokerResponse {
        // Start consuming
        $response = null;
        $correlationId = $message->getProperty('correlation_id');
        $subscriber = subscribe(
            "",
            BrokerResponse::class,
            function (AMQPMessage $message) use (&$response, $correlationId) {
                // is our message?
                if ($message->get_properties()["correlation_id"] != $correlationId) {
                    $message->nack(true);
                }
                $response = new BrokerResponse(
                    $message->getBody(),
                    $message->get_properties(),
                    $message->getConsumerTag()
                );
            }
        );

        // update reply to message property
        $message->setReplyTo($subscriber->getQueueName());

        // publish
        publish($message);

        // iterate consumer
        $until = time() + $timeout;
        do {
            // wait a while - prevent CPU load
            usleep(10000);
            $subscriber->iterate();
            // wait a while - prevent CPU load
            usleep(40000);
        } while ($until > time() && is_null($response));

        // close subscriber channel
        $subscriber->closeChannel();

        return $response;
    }
}