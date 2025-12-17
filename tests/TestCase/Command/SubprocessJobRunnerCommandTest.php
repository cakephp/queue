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

namespace Cake\Queue\Test\TestCase\Command;

use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Queue\Command\SubprocessJobRunnerCommand;
use Cake\TestSuite\TestCase;
use Enqueue\Null\NullMessage;
use Interop\Queue\Processor;
use ReflectionClass;
use TestApp\TestProcessor;

class SubprocessJobRunnerCommandTest extends TestCase
{
    /**
     * Test that executeJob processes a job successfully
     */
    public function testExecuteJobReturnsAck(): void
    {
        $jobData = [
            'messageClass' => NullMessage::class,
            'body' => [
                'class' => [TestProcessor::class, 'processReturnAck'],
                'args' => [],
            ],
            'properties' => [],
        ];

        $command = new SubprocessJobRunnerCommand();
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('executeJob');

        $result = $method->invoke($command, $jobData);

        $this->assertSame(Processor::ACK, $result);
    }

    /**
     * Test that executeJob handles REJECT response
     */
    public function testExecuteJobReturnsReject(): void
    {
        $jobData = [
            'messageClass' => NullMessage::class,
            'body' => [
                'class' => [TestProcessor::class, 'processReturnReject'],
                'args' => [],
            ],
            'properties' => [],
        ];

        $command = new SubprocessJobRunnerCommand();
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('executeJob');

        $result = $method->invoke($command, $jobData);

        $this->assertSame(Processor::REJECT, $result);
    }

    /**
     * Test that executeJob handles REQUEUE response
     */
    public function testExecuteJobReturnsRequeue(): void
    {
        $jobData = [
            'messageClass' => NullMessage::class,
            'body' => [
                'class' => [TestProcessor::class, 'processReturnRequeue'],
                'args' => [],
            ],
            'properties' => [],
        ];

        $command = new SubprocessJobRunnerCommand();
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('executeJob');

        $result = $method->invoke($command, $jobData);

        $this->assertSame(Processor::REQUEUE, $result);
    }

    /**
     * Test that executeJob handles job returning null (defaults to ACK)
     */
    public function testExecuteJobReturnsNull(): void
    {
        $jobData = [
            'messageClass' => NullMessage::class,
            'body' => [
                'class' => [TestProcessor::class, 'processReturnNull'],
                'args' => [],
            ],
            'properties' => [],
        ];

        $command = new SubprocessJobRunnerCommand();
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('executeJob');

        $result = $method->invoke($command, $jobData);

        $this->assertSame(Processor::ACK, $result);
    }

    /**
     * Test that executeJob handles properties correctly
     */
    public function testExecuteJobWithProperties(): void
    {
        $jobData = [
            'messageClass' => NullMessage::class,
            'body' => [
                'class' => [TestProcessor::class, 'processReturnAck'],
                'args' => [],
            ],
            'properties' => [
                'attempts' => 1,
                'custom_property' => 'test_value',
            ],
        ];

        $command = new SubprocessJobRunnerCommand();
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('executeJob');

        $result = $method->invoke($command, $jobData);

        $this->assertSame(Processor::ACK, $result);
    }

    /**
     * Test execute with invalid JSON input
     */
    public function testExecuteWithInvalidJson(): void
    {
        $command = $this->getMockBuilder(SubprocessJobRunnerCommand::class)
            ->onlyMethods(['readInput'])
            ->getMock();

        $command->expects($this->once())
            ->method('readInput')
            ->willReturn('invalid json {]');

        $args = $this->createStub(Arguments::class);
        $io = $this->createMock(ConsoleIo::class);

        $io->expects($this->once())
            ->method('out')
            ->with($this->stringContains('Invalid JSON input'));

        $result = $command->execute($args, $io);

        $this->assertSame(SubprocessJobRunnerCommand::CODE_ERROR, $result);
    }

    /**
     * Test execute with empty input
     */
    public function testExecuteWithEmptyInput(): void
    {
        $command = $this->getMockBuilder(SubprocessJobRunnerCommand::class)
            ->onlyMethods(['readInput'])
            ->getMock();

        $command->expects($this->once())
            ->method('readInput')
            ->willReturn('');

        $args = $this->createStub(Arguments::class);
        $io = $this->createMock(ConsoleIo::class);

        $io->expects($this->once())
            ->method('out')
            ->with($this->stringContains('No input received'));

        $result = $command->execute($args, $io);

        $this->assertSame(SubprocessJobRunnerCommand::CODE_ERROR, $result);
    }

    /**
     * Test execute with job that throws exception
     */
    public function testExecuteWithJobException(): void
    {
        $jobData = [
            'messageClass' => NullMessage::class,
            'body' => [
                'class' => [TestProcessor::class, 'processAndThrowException'],
                'args' => [],
            ],
            'properties' => [],
        ];

        $command = $this->getMockBuilder(SubprocessJobRunnerCommand::class)
            ->onlyMethods(['readInput'])
            ->getMock();

        $command->expects($this->once())
            ->method('readInput')
            ->willReturn(json_encode($jobData));

        $args = $this->createStub(Arguments::class);
        $io = $this->createMock(ConsoleIo::class);

        $io->expects($this->once())
            ->method('out')
            ->with($this->callback(function ($output) {
                $result = json_decode($output, true);

                return $result['success'] === false &&
                       isset($result['exception']) &&
                       in_array($result['exception']['class'], ['RuntimeException', 'Exception']);
            }));

        $result = $command->execute($args, $io);

        $this->assertSame(SubprocessJobRunnerCommand::CODE_SUCCESS, $result);
    }

    /**
     * Test outputResult method
     */
    public function testOutputResult(): void
    {
        $command = new SubprocessJobRunnerCommand();
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('outputResult');

        $io = $this->createMock(ConsoleIo::class);
        $io->expects($this->once())
            ->method('out')
            ->with('{"success":true,"result":"ack"}');

        $method->invoke($command, $io, ['success' => true, 'result' => 'ack']);
    }
}
