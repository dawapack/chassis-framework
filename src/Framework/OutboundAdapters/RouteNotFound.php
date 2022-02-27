<?php

declare(strict_types=1);

namespace Chassis\Framework\OutboundAdapters;

use Chassis\Framework\Brokers\Amqp\BrokerResponse;
use Chassis\Framework\Brokers\Amqp\MessageBags\MessageBagInterface;
use Chassis\Framework\Brokers\Exceptions\MessageBagFormatException;
use JsonException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class RouteNotFound extends OutboundAbstractAdapter
{
    protected const LOGGER_NOT_FOUND_COMPONENT = "route_not_found";

    /**
     * Use empty channel name (AMQP default)
     *
     * @var string
     */
    protected string $channelName = "";

    /**
     * Nobody cares about the implementation
     *
     * @param MessageBagInterface $message
     *
     * @return BrokerResponse|null
     * @throws ContainerExceptionInterface
     * @throws JsonException
     * @throws MessageBagFormatException
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(MessageBagInterface $message): ?BrokerResponse
    {
        if (!empty($message->getProperty("reply_to"))) {
            $this->send($this->createResponseMessage($message));
        }

        // log as info
        $this->application->logger()->info(
            "route not found",
            [
                "component" => self::LOGGER_NOT_FOUND_COMPONENT,
                "for_context" => $message->getProperties()
            ]
        );

        return null;
    }

    /**
     * @param MessageBagInterface $context
     *
     * @return BrokerResponse
     * @throws MessageBagFormatException
     * @throws JsonException
     */
    private function createResponseMessage(MessageBagInterface $context): BrokerResponse
    {
        return (new BrokerResponse([]))
            ->fromContext($context)
            ->setStatus(404, "NOT FOUND");
    }
}
