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
namespace Cake\Queue\Queue;

use Cake\Core\ContainerInterface;
use Cake\Queue\Job\Message;
use Enqueue\Consumption\Result;
use Error;
use Interop\Queue\Context;
use Interop\Queue\Message as QueueMessage;
use Interop\Queue\Processor as InteropProcessor;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Subprocess processor that executes jobs in isolated PHP processes.
 * Extends Processor to reuse event handling and processing logic (DRY principle).
 */
class SubprocessProcessor extends Processor
{
    /**
     * @param \Psr\Log\LoggerInterface $logger Logger instance
     * @param array<string, mixed> $config Subprocess configuration
     * @param \Cake\Core\ContainerInterface|null $container DI container instance
     */
    public function __construct(
        LoggerInterface $logger,
        protected readonly array $config = [],
        ?ContainerInterface $container = null,
    ) {
        parent::__construct($logger, $container);
    }

    /**
     * Process a message in a subprocess.
     * Overrides parent to execute in subprocess, but reuses parent's event dispatching.
     *
     * @param \Interop\Queue\Message $message Message.
     * @param \Interop\Queue\Context $context Context.
     * @return object|string with __toString method implemented
     */
    public function process(QueueMessage $message, Context $context): string|object
    {
        $this->dispatchEvent('Processor.message.seen', ['queueMessage' => $message]);

        $jobMessage = new Message($message, $context, $this->container);
        try {
            $jobMessage->getCallable();
        } catch (RuntimeException | Error $e) {
            $this->logger->debug('Invalid callable for message. Rejecting message from queue.');
            $this->dispatchEvent('Processor.message.invalid', ['message' => $jobMessage]);

            return InteropProcessor::REJECT;
        }

        $startTime = microtime(true) * 1000;
        $this->dispatchEvent('Processor.message.start', ['message' => $jobMessage]);

        try {
            $jobData = $this->prepareJobData($message);
            $subprocessResult = $this->executeInSubprocess($jobData);
            $response = $this->handleSubprocessResult($subprocessResult, $message);
        } catch (Throwable $throwable) {
            $message->setProperty('jobException', $throwable);

            $this->logger->debug(sprintf('Message encountered exception: %s', $throwable->getMessage()));
            $this->dispatchEvent('Processor.message.exception', [
                'message' => $jobMessage,
                'exception' => $throwable,
                'duration' => (int)((microtime(true) * 1000) - $startTime),
            ]);

            return Result::requeue('Exception occurred while processing message');
        }

        $duration = (int)((microtime(true) * 1000) - $startTime);

        if ($response === InteropProcessor::ACK) {
            $this->logger->debug('Message processed successfully');
            $this->dispatchEvent('Processor.message.success', [
                'message' => $jobMessage,
                'duration' => $duration,
            ]);

            return InteropProcessor::ACK;
        }

        if ($response === InteropProcessor::REJECT) {
            $this->logger->debug('Message processed with rejection');
            $this->dispatchEvent('Processor.message.reject', [
                'message' => $jobMessage,
                'duration' => $duration,
            ]);

            return InteropProcessor::REJECT;
        }

        $this->logger->debug('Message processed with failure, requeuing');
        $this->dispatchEvent('Processor.message.failure', [
            'message' => $jobMessage,
            'duration' => $duration,
        ]);

        return InteropProcessor::REQUEUE;
    }

    /**
     * Handle subprocess result and return appropriate response.
     *
     * @param array<string, mixed> $result Subprocess result
     * @param \Interop\Queue\Message $message Original message
     * @return string
     * @throws \RuntimeException
     */
    protected function handleSubprocessResult(array $result, QueueMessage $message): string
    {
        if ($result['success']) {
            return $result['result'];
        }

        if (isset($result['exception'])) {
            $exception = $this->reconstructException($result['exception']);
            $message->setProperty('jobException', $exception);

            throw $exception;
        }

        throw new RuntimeException($result['error'] ?? 'Subprocess execution failed');
    }

    /**
     * Prepare job data for subprocess execution.
     *
     * @param \Interop\Queue\Message $message Message
     * @return array<string, mixed>
     */
    protected function prepareJobData(QueueMessage $message): array
    {
        $body = json_decode($message->getBody(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid JSON in message body');
        }

        $properties = $message->getProperties();

        return [
            'messageClass' => get_class($message),
            'body' => $body,
            'properties' => $properties,
        ];
    }

    /**
     * Execute job in subprocess.
     *
     * @param array<string, mixed> $jobData Job data
     * @return array<string, mixed>
     */
    protected function executeInSubprocess(array $jobData): array
    {
        $command = $this->config['command'] ?? 'php bin/cake.php queue subprocess-runner';
        $timeout = $this->config['timeout'] ?? 300;

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            throw new RuntimeException('Failed to create subprocess');
        }

        $jobDataJson = json_encode($jobData);
        if ($jobDataJson !== false) {
            fwrite($pipes[0], $jobDataJson);
        }

        fclose($pipes[0]);

        $output = '';
        $errorOutput = '';
        $startTime = time();

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        while (true) {
            if ($timeout > 0 && (time() - $startTime) > $timeout) {
                proc_terminate($process, 9);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);

                return [
                    'success' => false,
                    'error' => sprintf('Subprocess execution timeout after %d seconds', $timeout),
                ];
            }

            $read = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;
            $selectResult = stream_select($read, $write, $except, 1);

            if ($selectResult === false) {
                break;
            }

            if (in_array($pipes[1], $read)) {
                $chunk = fread($pipes[1], 8192);
                if ($chunk !== false) {
                    $output .= $chunk;
                }
            }

            if (in_array($pipes[2], $read)) {
                $chunk = fread($pipes[2], 8192);
                if ($chunk !== false) {
                    $errorOutput .= $chunk;
                }
            }

            if (feof($pipes[1]) && feof($pipes[2])) {
                break;
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0 && empty($output)) {
            return [
                'success' => false,
                'error' => sprintf('Subprocess exited with code %d. Error: %s', $exitCode, $errorOutput),
            ];
        }

        $result = json_decode($output, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'Invalid JSON output from subprocess: ' . $output,
            ];
        }

        return $result;
    }

    /**
     * Reconstruct exception from array data.
     *
     * @param array<string, mixed> $exceptionData Exception data
     * @return \RuntimeException
     */
    protected function reconstructException(array $exceptionData): RuntimeException
    {
        $message = sprintf(
            '%s: %s in %s:%d',
            $exceptionData['class'] ?? 'Exception',
            $exceptionData['message'] ?? 'Unknown error',
            $exceptionData['file'] ?? 'unknown',
            $exceptionData['line'] ?? 0,
        );

        return new RuntimeException($message, (int)($exceptionData['code'] ?? 0));
    }
}
