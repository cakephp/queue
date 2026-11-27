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

use Cake\Queue\Bus\Stamp\ReceivedStamp;

/**
 * Pipeline phase an envelope is currently in.
 *
 * The dispatch bus handles envelopes before they reach the transport, the
 * consume bus handles them after. The `ReceivedStamp` added on receipt is
 * what tells the two apart.
 */
enum PhaseEnum: string
{
    case Dispatch = 'dispatch';
    case Consume = 'consume';

    /**
     * Detect the phase from the envelope stamps.
     *
     * @param \Cake\Queue\Bus\Envelope $envelope Envelope to inspect.
     * @return self
     */
    public static function fromEnvelope(Envelope $envelope): self
    {
        if ($envelope->getLastStamp(ReceivedStamp::class) instanceof StampInterface) {
            return self::Consume;
        }

        return self::Dispatch;
    }
}
