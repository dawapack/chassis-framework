<?php

declare(strict_types=1);

namespace Chassis\Framework\Workers;

use Chassis\Application;
use Chassis\Framework\Brokers\Amqp\Streamers\InfrastructureStreamer;
use Chassis\Framework\Brokers\Amqp\Streamers\SubscriberStreamer;
use Chassis\Framework\InterProcessCommunication\ChannelsInterface;
use Chassis\Framework\InterProcessCommunication\DataTransferObject\IPCMessage;
use Chassis\Framework\InterProcessCommunication\ParallelChannels;
use Chassis\Framework\Threads\Exceptions\ThreadConfigurationException;
use Throwable;

use function Chassis\Helpers\subscribe;

class Worker implements WorkerInterface
{
    private const LOGGER_COMPONENT_PREFIX = "worker_";
    private const LOOP_EACH_MS = 50;

    private Application $application;
    private ChannelsInterface $channels;
    private SubscriberStreamer $subscriberStreamer;

    /**
     * @param Application $application
     * @param ChannelsInterface $channels
     */
    public function __construct(
        Application $application,
        ChannelsInterface $channels
    ) {
        $this->application = $application;
        $this->channels = $channels;
    }

    /**
     * @return void
     */
    public function start(): void
    {
        try {
            $this->subscriberSetup();
            do {
                $startAt = microtime(true);
                // channel event poll & streamer iterate
                if (!$this->polling()) {
                    break;
                }
                // Wait a while - prevent CPU load
                $this->loopWait($startAt);
            } while (true);
        } catch (Throwable $reason) {
            // log this error & request respawning
            $this->application->logger()->error(
                $reason->getMessage(),
                [
                    'component' => self::LOGGER_COMPONENT_PREFIX . "exception",
                    'error' => $reason
                ]
            );
            $this->channels->sendTo(
                $this->channels->getThreadChannel(),
                (new IPCMessage())->set(ParallelChannels::METHOD_RESPAWN_REQUESTED)
            );
        }

        // Close subscriber streamer channel
        if (isset($this->subscriberStreamer)) {
            $this->subscriberStreamer->closeChannel();
        }
    }

    /**
     * @return bool
     */
    private function polling(): bool
    {
        // channel events pool
        $polling = $this->channels->eventsPoll();
        if ($this->channels->isAbortRequested()) {
            // send aborting message to main thread
            $this->channels->sendTo(
                $this->channels->getThreadChannel(),
                (new IPCMessage())->set(ParallelChannels::METHOD_ABORTING)
            );
            return false;
        }

        // subscriber iterate
        if (isset($this->subscriberStreamer)) {
            $this->subscriberStreamer->iterate();
        }

        return $polling;
    }

    /**
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function subscriberSetup(): void
    {
        $threadConfiguration = $this->application->get('threadConfiguration');
        switch ($threadConfiguration["threadType"]) {
            case "infrastructure":
                // Broker channels setup
                (new InfrastructureStreamer($this->application))->brokerChannelsSetup();
                break;
            case "configuration":
                // TODO: implement configuration listener - (centralized configuration server feature)
                break;
            case "worker":
                // wait a while - infrastructure must declare exchanges, queues & bindings
                usleep(rand(2500000, 4000000));
                // create subscriber
                $this->subscriberStreamer = subscribe(
                    $threadConfiguration["channelName"],
                    $threadConfiguration["handler"]
                );
                break;
            default:
                throw new ThreadConfigurationException("unknown thread type");
        }
    }

    /**
     * @param float $startAt
     *
     * @return void
     */
    private function loopWait(float $startAt): void
    {
        $loopWait = self::LOOP_EACH_MS - (round((microtime(true) - $startAt) * 1000));
        if ($loopWait > 0) {
            usleep(((int)$loopWait * 1000));
        }
    }
}
