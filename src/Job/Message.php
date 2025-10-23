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
 * @since         0.1.0
 * @license       https://opensource.org/licenses/MIT MIT License
 */
namespace Cake\Queue\Job;

use Cake\Core\ContainerInterface;
use Cake\Utility\Hash;
use Closure;
use Interop\Queue\Context;
use Interop\Queue\Message as QueueMessage;
use JsonSerializable;
use RuntimeException;

class Message implements JsonSerializable
{
    /**
     * @var array<string, mixed>
     */
    protected array $parsedBody;

    protected ?Closure $callable = null;

    /**
     * @param \Interop\Queue\Message $originalMessage Queue message.
     * @param \Interop\Queue\Context $context Context.
     * @param \Cake\Core\ContainerInterface|null $container DI container instance
     */
    public function __construct(
        protected readonly QueueMessage $originalMessage,
        protected readonly Context $context,
        protected readonly ?ContainerInterface $container = null,
    ) {
        $this->parsedBody = json_decode($originalMessage->getBody(), true);
    }

    /**
     * Get the queue context.
     *
     * @return \Interop\Queue\Context
     */
    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * Get the original queue message.
     *
     * @return \Interop\Queue\Message
     */
    public function getOriginalMessage(): QueueMessage
    {
        return $this->originalMessage;
    }

    /**
     * Get the parsed message body.
     *
     * @return array<string, mixed>
     */
    public function getParsedBody(): array
    {
        return $this->parsedBody;
    }

    /**
     * Get a closure containing the callable in the job.
     *
     * Supported callables include:
     * - array of [class, method]. The class will be constructed with no constructor parameters.
     *
     * @return \Closure
     */
    public function getCallable(): Closure
    {
        if ($this->callable instanceof Closure) {
            return $this->callable;
        }

        $target = $this->getTarget();
        if ($this->container && $this->container->has($target[0])) {
            $object = $this->container->get($target[0]);
        } else {
            $object = new $target[0]();
        }

        $this->callable = Closure::fromCallable([$object, $target[1]]);

        return $this->callable;
    }

    /**
     * Get the target class and method.
     *
     * @return array{string, string}
     * @phpstan-return array{class-string, string}
     */
    public function getTarget(): array
    {
        /** @var array{class-string, string}|null $target */
        $target = $this->parsedBody['class'] ?? null;

        if (!is_array($target) || count($target) !== 2) {
            throw new RuntimeException(sprintf(
                'Message class should be in the form `[class, method]` got `%s`',
                json_encode($target),
            ));
        }

        return $target;
    }

    /**
     * @param mixed $key Key
     * @param mixed $default Default value.
     * @return mixed
     */
    public function getArgument(mixed $key = null, mixed $default = null): mixed
    {
        // support old jobs that still use args key
        if (array_key_exists('data', $this->parsedBody)) {
            $data = $this->parsedBody['data'];
        } else {
            // support old jobs that still use args key
            $data = $this->parsedBody['args'][0];
        }

        if ($key === null) {
            return $data;
        }

        return Hash::get($data, $key, $default);
    }

    /**
     * The maximum number of attempts allowed by the job.
     *
     * @return int|null
     */
    public function getMaxAttempts(): ?int
    {
        $target = $this->getTarget();

        $class = $target[0];

        return $class::$maxAttempts ?? null;
    }

    /**
     * Convert the message to a string representation.
     *
     * @return string
     */
    public function __toString(): string
    {
        return (string)json_encode($this);
    }

    /**
     * Serialize the message to JSON.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->parsedBody;
    }
}
