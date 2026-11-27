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
use Cake\Queue\Bus\BusStackInterface;
use Cake\Queue\Bus\Dispatch;
use Cake\Queue\Bus\Envelope;
use Cake\Queue\Bus\Stamp\ReceivedStamp;
use Cake\Queue\Bus\Stamp\SentStamp;
use Cake\Queue\Bus\StampInterface;
use Cake\Queue\QueueManager;
use Enqueue\Client\Message as ClientMessage;
use LogicException;
use RuntimeException;

/**
 * Terminal dispatch middleware: publishes the envelope to the transport.
 *
 * Assembles the transport body from the dispatch message, so user middleware
 * enriching the envelope is reflected on the wire. Applies the transport options
 * and sends the event through `EnqueueClient::sendEvent()`, which emits the
 * `Queue.produce.success` / `Queue.produce.failure` events. Attaches a
 * `SentStamp` with the transport message identity on success.
 *
 * Refuses envelopes stamped `ReceivedStamp`: anything received from a
 * transport flows toward handling, never back into it (re-send-loop guard).
 *
 * The `metadata.stamps` wire key is reserved for the envelope stamps.
 * Dispatch metadata must not contain a `stamps` key; `QueueManager::push()`
 * rejects it. User middleware must treat it as read-only.
 */
final class SendMiddleware implements BusMiddlewareInterface
{
    /**
     * @inheritDoc
     */
    public function handle(Envelope $envelope, BusStackInterface $stack): Envelope
    {
        $dispatch = $envelope->getMessage();
        if (!$dispatch instanceof Dispatch) {
            throw new RuntimeException('SendMiddleware requires a Dispatch message.');
        }

        if ($envelope->getLastStamp(ReceivedStamp::class) instanceof StampInterface) {
            throw new LogicException('SendMiddleware refuses envelopes stamped ReceivedStamp.');
        }

        $body = $dispatch->toBody();
        $metadata = $dispatch->getMetadata();
        $envelopeMetadata = $envelope->toMetadataArray();
        if (isset($envelopeMetadata['stamps'])) {
            $metadata['stamps'] = $envelopeMetadata['stamps'];
        }

        if ($metadata !== []) {
            $body['metadata'] = $metadata;
        }

        $message = new ClientMessage($body);

        if ($dispatch->getDelay() !== null) {
            $message->setDelay($dispatch->getDelay());
        }

        if ($dispatch->getExpires() !== null) {
            $message->setExpire($dispatch->getExpires());
        }

        if ($dispatch->getPriority() !== null) {
            $message->setPriority($dispatch->getPriority());
        }

        $client = QueueManager::engine($dispatch->getConfigName());
        $client->sendEvent($dispatch->getQueue(), $message);

        $messageId = $message->getMessageId();

        return $envelope->withStamp(new SentStamp(is_string($messageId) && $messageId !== '' ? $messageId : null));
    }
}
