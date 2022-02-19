<?php

declare(strict_types=1);

namespace Chassis\Framework\Routers;

use Chassis\Framework\Brokers\Amqp\BrokerResponse;
use Chassis\Framework\Brokers\Amqp\MessageBags\MessageBagInterface;
use Chassis\Framework\Services\ServiceInterface;

use function Chassis\Helpers\publish;

class RouteDispatcher
{
    private string $method;
    private bool $invokable = false;

    /**
     * @param array|string $route
     * @param MessageBagInterface $message
     *
     * @return bool
     */
    public function dispatch($route, MessageBagInterface $message): bool
    {
        // broker service resolver
        $concreteService = $this->resolveRoute($route, $message);
        $response = $this->invokable
            ? ($concreteService)($message)
            : $concreteService->{$this->method}();

        // handle response
        if ($response instanceof BrokerResponse) {
            $this->dispatchResponse($response);
        }

        return true;
    }

    /**
     * @param array|string $route
     * @param MessageBagInterface $message
     *
     * @return ServiceInterface
     */
    protected function resolveRoute($route, MessageBagInterface $message): ServiceInterface
    {
        if (is_string($route)) {
            $this->invokable = true;
            return new $route();
        }

        list($className, $this->method) = $route;
        return new $className($message);
    }

    /**
     * @param BrokerResponse $response
     *
     * @return void
     */
    protected function dispatchResponse(BrokerResponse $response): void
    {
        publish($response);
    }
}
