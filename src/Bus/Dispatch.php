<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org/)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org/)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         3.0.0
 * @license       https://opensource.org/licenses/MIT MIT License
 */
namespace Cake\Queue\Bus;

use InvalidArgumentException;

/**
 * A job waiting to be dispatched.
 *
 * Immutable value object carrying everything the dispatch bus needs:
 * the resolved job target, the serialized payload, routing, transport
 * options and the metadata base. The transport body is derived
 * from it (plus the final envelope stamps) by `SendMiddleware`, so the
 * wire shape stays in one place.
 */
final class Dispatch
{
    /**
     * @param class-string $class Resolved job class name.
     * @param string $method Job method name.
     * @param array<string, mixed> $data Serialized job payload.
     * @param class-string|null $dtoClass DTO class the payload was serialized from, if any.
     * @param string $queue Queue name to send to.
     * @param string $configName Queue config name.
     * @param int|null $delay Seconds to delay delivery, if any.
     * @param int|null $expires Seconds after which the message expires, if any.
     * @param string|null $priority Transport priority, if any.
     * @param array<string, mixed> $metadata Metadata base, passed through verbatim.
     */
    public function __construct(
        private readonly string $class,
        private readonly string $method,
        private readonly array $data,
        private readonly ?string $dtoClass,
        private readonly string $queue,
        private readonly string $configName,
        private readonly ?int $delay = null,
        private readonly ?int $expires = null,
        private readonly ?string $priority = null,
        private readonly array $metadata = [],
    ) {
        $names = ['class' => $class, 'method' => $method, 'queue' => $queue, 'configName' => $configName];
        foreach ($names as $name => $value) {
            if ($value === '') {
                throw new InvalidArgumentException(sprintf('Dispatch `%s` must be a non-empty string.', $name));
            }
        }

        if ($dtoClass !== null && !class_exists($dtoClass)) {
            throw new InvalidArgumentException(sprintf('Dispatch `dtoClass` `%s` does not exist.', $dtoClass));
        }

        if (!class_exists($class)) {
            throw new InvalidArgumentException(sprintf('Dispatch `class` `%s` does not exist.', $class));
        }

        if ($delay !== null && $delay < 0) {
            throw new InvalidArgumentException('Dispatch `delay` must be null or a non-negative integer.');
        }

        if ($expires !== null && $expires < 0) {
            throw new InvalidArgumentException('Dispatch `expires` must be null or a non-negative integer.');
        }

        if ($priority !== null && $priority === '') {
            throw new InvalidArgumentException('Dispatch `priority` must be null or a non-empty string.');
        }

        if (array_key_exists('stamps', $metadata)) {
            throw new InvalidArgumentException(
                'Dispatch `metadata` must not contain a `stamps` key: it is reserved for the bus.',
            );
        }
    }

    /**
     * Get the resolved job class name.
     *
     * @return class-string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * Get the job method name.
     *
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get the serialized job payload.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get the DTO class the payload was serialized from, if any.
     *
     * @return class-string|null
     */
    public function getDtoClass(): ?string
    {
        return $this->dtoClass;
    }

    /**
     * Get the queue name to send to.
     *
     * @return string
     */
    public function getQueue(): string
    {
        return $this->queue;
    }

    /**
     * Get the queue config name.
     *
     * @return string
     */
    public function getConfigName(): string
    {
        return $this->configName;
    }

    /**
     * Get the delivery delay in seconds, if any.
     */
    public function getDelay(): ?int
    {
        return $this->delay;
    }

    /**
     * Get the message expiry in seconds, if any.
     */
    public function getExpires(): ?int
    {
        return $this->expires;
    }

    /**
     * Get the transport priority, if any.
     */
    public function getPriority(): ?string
    {
        return $this->priority;
    }

    /**
     * Get the metadata base.
     *
     * Passed through verbatim into the transport body; on consume the
     * envelope carries only `data` plus stamps — read payload metadata
     * there via the `JobMessageStamp` message instead.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Return a copy routed to a different queue.
     *
     * @param string $queue Queue name.
     * @return static
     */
    public function withQueue(string $queue): static
    {
        return new self(
            $this->class,
            $this->method,
            $this->data,
            $this->dtoClass,
            $queue,
            $this->configName,
            $this->delay,
            $this->expires,
            $this->priority,
            $this->metadata,
        );
    }

    /**
     * Return a copy with a different delivery delay.
     *
     * @param int|null $delay Seconds to delay delivery, if any.
     * @return static
     */
    public function withDelay(?int $delay): static
    {
        return new self(
            $this->class,
            $this->method,
            $this->data,
            $this->dtoClass,
            $this->queue,
            $this->configName,
            $delay,
            $this->expires,
            $this->priority,
            $this->metadata,
        );
    }

    /**
     * Return a copy with a different message expiry.
     *
     * @param int|null $expires Seconds after which the message expires, if any.
     * @return static
     */
    public function withExpires(?int $expires): static
    {
        return new self(
            $this->class,
            $this->method,
            $this->data,
            $this->dtoClass,
            $this->queue,
            $this->configName,
            $this->delay,
            $expires,
            $this->priority,
            $this->metadata,
        );
    }

    /**
     * Return a copy with a different transport priority.
     *
     * @param string|null $priority Transport priority, if any.
     * @return static
     */
    public function withPriority(?string $priority): static
    {
        return new self(
            $this->class,
            $this->method,
            $this->data,
            $this->dtoClass,
            $this->queue,
            $this->configName,
            $this->delay,
            $this->expires,
            $priority,
            $this->metadata,
        );
    }

    /**
     * Build the transport body without the `metadata` section.
     *
     * `args[0]` is a legacy duplicate of `data` for old consumers;
     * `data` is authoritative. `delay`, `expires` and `priority` travel
     * on the `Dispatch`, not in the body — `SendMiddleware` applies them
     * to the transport message.
     *
     * Metadata is assembled by `SendMiddleware` from this base plus the
     * final envelope stamps, so user middleware enriching the envelope
     * is reflected on the wire.
     *
     * @return array<string, mixed>
     */
    public function toBody(): array
    {
        $body = [
            'class' => [$this->class, $this->method],
            'args' => [$this->data],
            'data' => $this->data,
            'requeueOptions' => [
                'config' => $this->configName,
                'priority' => $this->priority,
                'queue' => $this->queue,
            ],
        ];
        if ($this->dtoClass !== null) {
            $body['dtoClass'] = $this->dtoClass;
        }

        return $body;
    }
}
