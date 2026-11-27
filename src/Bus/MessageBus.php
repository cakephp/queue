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
use Traversable;

/**
 * Ordered middleware pipeline for envelopes.
 *
 * The bus owns no transport and no handler registry. It only runs the
 * configured middlewares in order around a terminal stage which leaves
 * the envelope unchanged. Dispatch and consume pipelines share this
 * implementation with different middleware lists.
 */
class MessageBus
{
    /**
     * @var list<\Cake\Queue\Bus\BusMiddlewareInterface>
     */
    protected array $middleware = [];

    /**
     * @param iterable<\Cake\Queue\Bus\BusMiddlewareInterface> $middleware Ordered middleware list.
     */
    public function __construct(iterable $middleware = [])
    {
        $resolved = $middleware instanceof Traversable
            ? iterator_to_array($middleware, false)
            : array_values($middleware);

        foreach ($resolved as $item) {
            if (!$item instanceof BusMiddlewareInterface) {
                throw new InvalidArgumentException(sprintf(
                    'Bus middleware must implement %s, `%s` given.',
                    BusMiddlewareInterface::class,
                    get_debug_type($item),
                ));
            }
        }

        $this->middleware = $resolved;
    }

    /**
     * Wrap a message (or envelope) with stamps and run the pipeline.
     *
     * @param object $message Message or envelope.
     * @param array<\Cake\Queue\Bus\StampInterface> $stamps Extra stamps to attach.
     * @return \Cake\Queue\Bus\Envelope
     */
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        return $this->handle(Envelope::wrap($message, $stamps));
    }

    /**
     * Run the pipeline for an envelope.
     *
     * @param \Cake\Queue\Bus\Envelope $envelope Envelope to handle.
     * @return \Cake\Queue\Bus\Envelope
     */
    public function handle(Envelope $envelope): Envelope
    {
        $middleware = $this->middleware;

        $stack = new class ($middleware) implements BusStackInterface {
            private int $position = 0;

            /**
             * @param list<\Cake\Queue\Bus\BusMiddlewareInterface> $middleware
             */
            public function __construct(
                private array $middleware,
            ) {
            }

            /**
             * Return the next middleware in the chain.
             *
             * @return \Cake\Queue\Bus\BusMiddlewareInterface
             */
            public function next(): BusMiddlewareInterface
            {
                if (!isset($this->middleware[$this->position])) {
                    return new class implements BusMiddlewareInterface {
                        /**
                         * Leave the envelope unchanged.
                         *
                         * @param \Cake\Queue\Bus\Envelope $envelope Envelope to handle.
                         * @param \Cake\Queue\Bus\BusStackInterface $stack Stack to resolve the next middleware.
                         * @return \Cake\Queue\Bus\Envelope
                         */
                        public function handle(Envelope $envelope, BusStackInterface $stack): Envelope
                        {
                            return $envelope;
                        }
                    };
                }

                return $this->middleware[$this->position++];
            }
        };

        return $stack->next()->handle($envelope, $stack);
    }
}
