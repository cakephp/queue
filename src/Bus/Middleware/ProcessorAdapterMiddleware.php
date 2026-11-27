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
namespace Cake\Queue\Bus\Middleware;

use Cake\Queue\Bus\BusMiddlewareInterface;
use Cake\Queue\Bus\BusProcessor;
use Cake\Queue\Bus\BusStackInterface;
use Cake\Queue\Bus\Envelope;
use Cake\Queue\Bus\Stamp\HandledStamp;
use Cake\Queue\Bus\Stamp\JobMessageStamp;
use Interop\Queue\Processor as InteropProcessor;
use RuntimeException;
use Throwable;

/**
 * Terminal consume middleware adapting a custom `InteropProcessor`.
 *
 * Deliberately transparent: it runs the custom processor exactly as the
 * worker would without the bus and wraps its outcome in a `HandledStamp`,
 * so `$config['processor']` overrides keep working inside the consume
 * pipeline. It emits no lifecycle events and writes no logs — observability
 * stays with the custom processor itself (the `Queue.listener` is attached
 * to its event manager when it has one, exactly as before the bus). The
 * `NO_REQUEUE` mark is control state, not observability: it tells
 * `BusProcessor` that a bus throwable must bubble instead of degrading a
 * produced outcome into a requeue.
 */
final class ProcessorAdapterMiddleware implements BusMiddlewareInterface
{
    /**
     * @param \Interop\Queue\Processor $processor Custom processor instance.
     */
    public function __construct(
        protected readonly InteropProcessor $processor,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function handle(Envelope $envelope, BusStackInterface $stack): Envelope
    {
        $jobMessage = $envelope->getLastStamp(JobMessageStamp::class)?->getMessage();
        if ($jobMessage === null) {
            throw new RuntimeException('ProcessorAdapterMiddleware requires a JobMessageStamp.');
        }

        try {
            $result = $this->processor->process(
                $jobMessage->getOriginalMessage(),
                $jobMessage->getContext(),
            );
        } catch (Throwable $throwable) {
            $jobMessage->getOriginalMessage()->setProperty(BusProcessor::NO_REQUEUE_PROPERTY, true);

            throw $throwable;
        }

        $jobMessage->getOriginalMessage()->setProperty(BusProcessor::NO_REQUEUE_PROPERTY, true);

        return $envelope->withStamp(new HandledStamp($result));
    }
}
