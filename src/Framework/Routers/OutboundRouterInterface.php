<?php

declare(strict_types=1);

namespace Chassis\Framework\Routers;

use Chassis\Framework\Brokers\Amqp\BrokerResponse;
use Chassis\Framework\Brokers\Amqp\MessageBags\MessageBagInterface;
use Chassis\Framework\Routers\Exceptions\RouteNotFoundException;

interface OutboundRouterInterface
{
    /**
     * @param string $operation
     * @param MessageBagInterface $message
     *
     * @return BrokerResponse|null
     *
     * @throws RouteNotFoundException
     */
    public function route(string $operation, MessageBagInterface $message): ?BrokerResponse;
}
