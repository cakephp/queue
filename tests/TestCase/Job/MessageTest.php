<?php
declare(strict_types=1);

namespace Queue\Test\TestCase\Job;


use Cake\TestSuite\TestCase;
use Enqueue\Fs\FsContext;
use Enqueue\Fs\FsMessage;
use Enqueue\Null\NullConnectionFactory;
use Enqueue\Null\NullMessage;
use Queue\Job\Message;

class MessageTest extends TestCase
{
    /**
     * Test getters methods
     *
     * @return void
     */
    public function testConstructorAndGetters()
    {
        $callable =  ["App\\Job\\ExampleJob","execute"];
        $data = "sample data " . time();
        $id = 7;
        $args = compact('id', 'data');
        $parsedBody = [
            "queue" => "default",
            "class" => $callable,
            "args" => [$args]
        ];
        $messageBody = json_encode($parsedBody);
        $connectionFactory = new NullConnectionFactory();

        $context = $connectionFactory->createContext();
        $originalMessage = new NullMessage($messageBody);
        $message = new Message($originalMessage, $context);

        $this->assertSame($context, $message->getContext());
        $this->assertSame($originalMessage, $message->getOriginalMessage());
        $this->assertSame($parsedBody, $message->getParsedBody());
        $this->assertSame($callable, $message->getCallable());
        $this->assertSame($args, $message->getArgument());
        $this->assertSame($id, $message->getArgument('id'));
        $this->assertSame($data, $message->getArgument('data', 'ignore_this'));
        $this->assertSame('should_use_this', $message->getArgument('unknown', 'should_use_this'));
        $this->assertSame(null, $message->getArgument('unknown'));
        $actualJson = json_encode($message);
        $this->assertSame($messageBody, $actualJson);
        $actualToStringValue = (string)$message;
        $this->assertSame($messageBody, $actualToStringValue);
    }
}
