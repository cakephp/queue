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

namespace Cake\Queue\Test\TestCase\Queue;

use Cake\Log\Engine\ArrayLog;
use Cake\Queue\Queue\SubprocessProcessor;
use Cake\TestSuite\TestCase;
use Enqueue\Null\NullConnectionFactory;
use Enqueue\Null\NullMessage;
use Interop\Queue\Processor as InteropProcessor;
use ReflectionClass;
use RuntimeException;
use TestApp\TestProcessor;

class SubprocessProcessorTest extends TestCase
{
    /**
     * Test prepareJobData method
     */
    public function testPrepareJobData(): void
    {
        $messageBody = [
            'class' => [TestProcessor::class, 'processReturnAck'],
            'args' => ['test' => 'data'],
        ];
        $queueMessage = new NullMessage(json_encode($messageBody) ?: '');
        $queueMessage->setProperty('attempts', 1);

        $logger = new ArrayLog();
        $processor = new SubprocessProcessor($logger);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('prepareJobData');

        $jobData = $method->invoke($processor, $queueMessage);

        $this->assertArrayHasKey('messageClass', $jobData);
        $this->assertArrayHasKey('body', $jobData);
        $this->assertArrayHasKey('properties', $jobData);
        $this->assertSame($messageBody, $jobData['body']);
        $this->assertArrayHasKey('attempts', $jobData['properties']);
        $this->assertSame(1, $jobData['properties']['attempts']);
    }

    /**
     * Test reconstructException method
     */
    public function testReconstructException(): void
    {
        $exceptionData = [
            'class' => 'RuntimeException',
            'message' => 'Test error',
            'code' => 500,
            'file' => '/path/to/file.php',
            'line' => 42,
        ];

        $logger = new ArrayLog();
        $processor = new SubprocessProcessor($logger);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('reconstructException');

        $exception = $method->invoke($processor, $exceptionData);

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertStringContainsString('Test error', $exception->getMessage());
        $this->assertStringContainsString('RuntimeException', $exception->getMessage());
        $this->assertSame(500, $exception->getCode());
    }

    /**
     * Test handleSubprocessResult with success response
     */
    public function testHandleSubprocessResultSuccess(): void
    {
        $result = [
            'success' => true,
            'result' => InteropProcessor::ACK,
        ];

        $messageBody = ['class' => [TestProcessor::class, 'processReturnAck'], 'args' => []];
        $queueMessage = new NullMessage(json_encode($messageBody) ?: '');

        $logger = new ArrayLog();
        $processor = new SubprocessProcessor($logger);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('handleSubprocessResult');

        $actual = $method->invoke($processor, $result, $queueMessage);

        $this->assertSame(InteropProcessor::ACK, $actual);
    }

    /**
     * Test handleSubprocessResult with reject response
     */
    public function testHandleSubprocessResultReject(): void
    {
        $result = [
            'success' => true,
            'result' => InteropProcessor::REJECT,
        ];

        $messageBody = ['class' => [TestProcessor::class, 'processReturnReject'], 'args' => []];
        $queueMessage = new NullMessage(json_encode($messageBody) ?: '');

        $logger = new ArrayLog();
        $processor = new SubprocessProcessor($logger);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('handleSubprocessResult');

        $actual = $method->invoke($processor, $result, $queueMessage);

        $this->assertSame(InteropProcessor::REJECT, $actual);
    }

    /**
     * Test handleSubprocessResult with requeue response
     */
    public function testHandleSubprocessResultRequeue(): void
    {
        $result = [
            'success' => true,
            'result' => InteropProcessor::REQUEUE,
        ];

        $messageBody = ['class' => [TestProcessor::class, 'processReturnRequeue'], 'args' => []];
        $queueMessage = new NullMessage(json_encode($messageBody) ?: '');

        $logger = new ArrayLog();
        $processor = new SubprocessProcessor($logger);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('handleSubprocessResult');

        $actual = $method->invoke($processor, $result, $queueMessage);

        $this->assertSame(InteropProcessor::REQUEUE, $actual);
    }

    /**
     * Test handleSubprocessResult with exception
     */
    public function testHandleSubprocessResultWithException(): void
    {
        $result = [
            'success' => false,
            'exception' => [
                'class' => 'RuntimeException',
                'message' => 'Test error',
                'code' => 500,
                'file' => '/path/to/file.php',
                'line' => 42,
            ],
        ];

        $messageBody = ['class' => [TestProcessor::class, 'processAndThrowException'], 'args' => []];
        $queueMessage = new NullMessage(json_encode($messageBody) ?: '');

        $logger = new ArrayLog();
        $processor = new SubprocessProcessor($logger);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('handleSubprocessResult');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Test error');

        $method->invoke($processor, $result, $queueMessage);
    }

    /**
     * Test handleSubprocessResult with error message
     */
    public function testHandleSubprocessResultWithError(): void
    {
        $result = [
            'success' => false,
            'error' => 'Subprocess execution failed',
        ];

        $messageBody = ['class' => [TestProcessor::class, 'processReturnAck'], 'args' => []];
        $queueMessage = new NullMessage(json_encode($messageBody) ?: '');

        $logger = new ArrayLog();
        $processor = new SubprocessProcessor($logger);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('handleSubprocessResult');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Subprocess execution failed');

        $method->invoke($processor, $result, $queueMessage);
    }

    /**
     * Test real subprocess execution with ACK result
     */
    public function testRealSubprocessExecutionAck(): void
    {
        $messageBody = [
            'class' => [TestProcessor::class, 'processReturnAck'],
            'args' => [],
        ];
        $queueMessage = new NullMessage(json_encode($messageBody) ?: '');

        $logger = new ArrayLog();
        $config = [
            'command' => 'php ' . ROOT . 'bin/cake.php queue subprocess-runner',
        ];
        $processor = new SubprocessProcessor($logger, $config);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('executeInSubprocess');
        $prepareMethod = $reflection->getMethod('prepareJobData');

        $jobData = $prepareMethod->invoke($processor, $queueMessage);
        $result = $method->invoke($processor, $jobData);

        $this->assertTrue($result['success']);
        $this->assertSame(InteropProcessor::ACK, $result['result']);
    }

    /**
     * Test real subprocess execution with REJECT result
     */
    public function testRealSubprocessExecutionReject(): void
    {
        $messageBody = [
            'class' => [TestProcessor::class, 'processReturnReject'],
            'args' => [],
        ];
        $queueMessage = new NullMessage(json_encode($messageBody) ?: '');

        $logger = new ArrayLog();
        $config = [
            'command' => 'php ' . ROOT . 'bin/cake.php queue subprocess-runner',
        ];
        $processor = new SubprocessProcessor($logger, $config);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('executeInSubprocess');
        $prepareMethod = $reflection->getMethod('prepareJobData');

        $jobData = $prepareMethod->invoke($processor, $queueMessage);
        $result = $method->invoke($processor, $jobData);

        $this->assertTrue($result['success']);
        $this->assertSame(InteropProcessor::REJECT, $result['result']);
    }

    /**
     * Test real subprocess execution with exception
     */
    public function testRealSubprocessExecutionWithException(): void
    {
        $messageBody = [
            'class' => [TestProcessor::class, 'processAndThrowException'],
            'args' => [],
        ];
        $queueMessage = new NullMessage(json_encode($messageBody) ?: '');

        $logger = new ArrayLog();
        $config = [
            'command' => 'php ' . ROOT . 'bin/cake.php queue subprocess-runner',
        ];
        $processor = new SubprocessProcessor($logger, $config);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('executeInSubprocess');
        $prepareMethod = $reflection->getMethod('prepareJobData');

        $jobData = $prepareMethod->invoke($processor, $queueMessage);
        $result = $method->invoke($processor, $jobData);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('exception', $result);
        $this->assertContains($result['exception']['class'], ['RuntimeException', 'Exception']);
    }

    /**
     * Test subprocess timeout handling
     */
    public function testSubprocessTimeout(): void
    {
        $messageBody = [
            'class' => [TestProcessor::class, 'processReturnAck'],
            'args' => [],
        ];

        $logger = new ArrayLog();
        $config = [
            'command' => 'php ' . ROOT . 'bin/cake.php queue subprocess-runner',
            'timeout' => 1,
        ];
        $processor = new SubprocessProcessor($logger, $config);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('executeInSubprocess');

        // Create job data that simulates a long-running process
        $jobData = [
            'messageClass' => NullMessage::class,
            'body' => $messageBody,
            'properties' => [],
        ];

        $result = $method->invoke($processor, $jobData);

        // Should complete successfully (fast job) or timeout
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    /**
     * Test subprocess with invalid command (non-existent binary)
     */
    public function testSubprocessWithInvalidCommand(): void
    {
        $messageBody = [
            'class' => [TestProcessor::class, 'processReturnAck'],
            'args' => [],
        ];
        $message = new NullMessage(json_encode($messageBody) ?: '');

        $logger = new ArrayLog();
        $config = [
            'command' => '/nonexistent/binary queue subprocess-runner',
        ];
        $processor = new SubprocessProcessor($logger, $config);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('executeInSubprocess');
        $prepareMethod = $reflection->getMethod('prepareJobData');

        $jobData = $prepareMethod->invoke($processor, $message);
        $result = $method->invoke($processor, $jobData);

        // Invalid command will fail with an error result
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Test subprocess with invalid JSON in message body
     */
    public function testPrepareJobDataWithInvalidJson(): void
    {
        $queueMessage = new NullMessage('invalid json {]');

        $logger = new ArrayLog();
        $processor = new SubprocessProcessor($logger);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('prepareJobData');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON in message body');

        $method->invoke($processor, $queueMessage);
    }

    /**
     * Test executeInSubprocess returns error when subprocess returns invalid JSON
     */
    public function testExecuteInSubprocessWithInvalidJsonOutput(): void
    {
        $logger = new ArrayLog();
        $config = [
            'command' => 'echo invalid-json-output',
        ];
        $processor = new SubprocessProcessor($logger, $config);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('executeInSubprocess');

        $jobData = [
            'messageClass' => NullMessage::class,
            'body' => ['class' => [TestProcessor::class, 'processReturnAck'], 'args' => []],
            'properties' => [],
        ];

        $result = $method->invoke($processor, $jobData);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Invalid JSON output from subprocess', $result['error']);
    }

    /**
     * Test executeInSubprocess handles non-zero exit code
     */
    public function testExecuteInSubprocessWithNonZeroExitCode(): void
    {
        $logger = new ArrayLog();
        $config = [
            'command' => 'php -r "exit(1);"',
        ];
        $processor = new SubprocessProcessor($logger, $config);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('executeInSubprocess');

        $jobData = [
            'messageClass' => NullMessage::class,
            'body' => ['class' => [TestProcessor::class, 'processReturnAck'], 'args' => []],
            'properties' => [],
        ];

        $result = $method->invoke($processor, $jobData);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Test full process() method with real subprocess execution
     */
    public function testProcessJobInSubprocess(): void
    {
        $messageBody = [
            'class' => [TestProcessor::class, 'processReturnAck'],
            'args' => [],
        ];
        $queueMessage = new NullMessage(json_encode($messageBody) ?: '');

        $logger = new ArrayLog();
        $config = [
            'command' => 'php ' . ROOT . 'bin/cake.php queue subprocess-runner',
        ];
        $processor = new SubprocessProcessor($logger, $config);

        $context = (new NullConnectionFactory())->createContext();
        $result = $processor->process($queueMessage, $context);

        $this->assertSame(InteropProcessor::ACK, $result);
    }

    /**
     * Test full process() with job that rejects
     */
    public function testProcessJobInSubprocessReject(): void
    {
        $messageBody = [
            'class' => [TestProcessor::class, 'processReturnReject'],
            'args' => [],
        ];
        $queueMessage = new NullMessage(json_encode($messageBody) ?: '');

        $logger = new ArrayLog();
        $config = [
            'command' => 'php ' . ROOT . 'bin/cake.php queue subprocess-runner',
        ];
        $processor = new SubprocessProcessor($logger, $config);

        $context = (new NullConnectionFactory())->createContext();
        $result = $processor->process($queueMessage, $context);

        $this->assertSame(InteropProcessor::REJECT, $result);
    }

    /**
     * Test full process() with job that throws exception
     */
    public function testProcessJobInSubprocessWithException(): void
    {
        $messageBody = [
            'class' => [TestProcessor::class, 'processAndThrowException'],
            'args' => [],
        ];
        $queueMessage = new NullMessage(json_encode($messageBody) ?: '');

        $logger = new ArrayLog();
        $config = [
            'command' => 'php ' . ROOT . 'bin/cake.php queue subprocess-runner',
        ];
        $processor = new SubprocessProcessor($logger, $config);

        $context = (new NullConnectionFactory())->createContext();
        $result = $processor->process($queueMessage, $context);

        // Should requeue on exception - result can be Result object or string
        /** @phpstan-ignore cast.string */
        $this->assertStringContainsString('requeue', (string)$result);
    }

    /**
     * Test subprocess with maxOutputSize limit exceeded
     */
    public function testSubprocessMaxOutputSizeExceeded(): void
    {
        $logger = new ArrayLog();
        $config = [
            'command' => 'php -r "echo str_repeat(\'a\', 10000);"',
            'maxOutputSize' => 100, // Very small limit
            'timeout' => 5,
        ];
        $processor = new SubprocessProcessor($logger, $config);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('executeInSubprocess');

        $jobData = [
            'messageClass' => NullMessage::class,
            'body' => ['class' => [TestProcessor::class, 'processReturnAck'], 'args' => []],
            'properties' => [],
        ];

        $result = $method->invoke($processor, $jobData);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('output exceeded maximum size', $result['error']);
    }

    /**
     * Test subprocess with maxOutputSize limit on stderr
     */
    public function testSubprocessMaxErrorOutputSizeExceeded(): void
    {
        $logger = new ArrayLog();
        $config = [
            'command' => 'php -r "fwrite(STDERR, str_repeat(\'e\', 10000));"',
            'maxOutputSize' => 100, // Very small limit
            'timeout' => 5,
        ];
        $processor = new SubprocessProcessor($logger, $config);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('executeInSubprocess');

        $jobData = [
            'messageClass' => NullMessage::class,
            'body' => ['class' => [TestProcessor::class, 'processReturnAck'], 'args' => []],
            'properties' => [],
        ];

        $result = $method->invoke($processor, $jobData);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('error output exceeded maximum size', $result['error']);
    }

    /**
     * Test subprocess handles normal sized output correctly
     */
    public function testSubprocessWithNormalOutputSize(): void
    {
        $messageBody = [
            'class' => [TestProcessor::class, 'processReturnAck'],
            'args' => [],
        ];
        $queueMessage = new NullMessage(json_encode($messageBody) ?: '');

        $logger = new ArrayLog();
        $config = [
            'command' => 'php ' . ROOT . 'bin/cake.php queue subprocess-runner',
            'maxOutputSize' => 1048576, // 1MB - normal size
            'timeout' => 30,
        ];
        $processor = new SubprocessProcessor($logger, $config);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('executeInSubprocess');
        $prepareMethod = $reflection->getMethod('prepareJobData');

        $jobData = $prepareMethod->invoke($processor, $queueMessage);
        $result = $method->invoke($processor, $jobData);

        $this->assertTrue($result['success']);
        $this->assertSame(InteropProcessor::ACK, $result['result']);
    }

    /**
     * Test executeInSubprocess with very short timeout
     */
    public function testSubprocessWithVeryShortTimeout(): void
    {
        $logger = new ArrayLog();
        $config = [
            'command' => 'php -r "sleep(5);"',
            'timeout' => 1, // 1 second timeout
        ];
        $processor = new SubprocessProcessor($logger, $config);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('executeInSubprocess');

        $jobData = [
            'messageClass' => NullMessage::class,
            'body' => ['class' => [TestProcessor::class, 'processReturnAck'], 'args' => []],
            'properties' => [],
        ];

        $result = $method->invoke($processor, $jobData);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('timeout', $result['error']);
    }
}
