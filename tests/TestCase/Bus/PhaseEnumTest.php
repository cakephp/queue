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

namespace Cake\Queue\Test\TestCase\Bus;

use Cake\Queue\Bus\Envelope;
use Cake\Queue\Bus\PhaseEnum;
use Cake\Queue\Bus\Stamp\ReceivedStamp;
use Cake\TestSuite\TestCase;
use TestApp\Bus\TestMessage;

class PhaseEnumTest extends TestCase
{
    public function testFromEnvelopeWithoutReceivedStampIsDispatch(): void
    {
        $this->assertSame(PhaseEnum::Dispatch, PhaseEnum::fromEnvelope(new Envelope(new TestMessage('hello'))));
    }

    public function testFromEnvelopeWithReceivedStampIsConsume(): void
    {
        $envelope = (new Envelope(new TestMessage('hello')))->withStamp(new ReceivedStamp('default'));

        $this->assertSame(PhaseEnum::Consume, PhaseEnum::fromEnvelope($envelope));
    }

    public function testBackedValues(): void
    {
        $this->assertSame('dispatch', PhaseEnum::Dispatch->value);
        $this->assertSame('consume', PhaseEnum::Consume->value);
        $this->assertSame(PhaseEnum::Dispatch, PhaseEnum::from('dispatch'));
    }
}
