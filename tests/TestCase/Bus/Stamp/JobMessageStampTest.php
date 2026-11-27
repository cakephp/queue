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

namespace Cake\Queue\Test\TestCase\Bus\Stamp;

use Cake\Queue\Bus\Stamp\JobMessageStamp;
use Cake\Queue\Job\Message;
use Cake\TestSuite\TestCase;
use Enqueue\Null\NullConnectionFactory;
use Enqueue\Null\NullMessage;
use LogicException;

class JobMessageStampTest extends TestCase
{
    public function testGetMessage(): void
    {
        $message = $this->jobMessage();
        $stamp = new JobMessageStamp($message);

        $this->assertSame($message, $stamp->getMessage());
    }

    public function testToArrayThrows(): void
    {
        $stamp = new JobMessageStamp($this->jobMessage());

        $this->expectException(LogicException::class);

        $stamp->toArray();
    }

    public function testFromArrayThrows(): void
    {
        $this->expectException(LogicException::class);

        JobMessageStamp::fromArray(['type' => 'JobMessageStamp', 'v' => 1]);
    }

    protected function jobMessage(): Message
    {
        $context = (new NullConnectionFactory())->createContext();
        $body = (string)json_encode([
            'class' => ['App\\Job\\ExampleJob', 'execute'],
            'data' => ['id' => 1],
        ]);

        return new Message(new NullMessage($body), $context);
    }
}
