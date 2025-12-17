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
namespace Cake\Queue\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Core\ContainerInterface;
use Cake\Log\Engine\ConsoleLog;
use Cake\Log\Log;
use Cake\Queue\Job\Message;
use Cake\Queue\Queue\Processor;
use Enqueue\Null\NullConnectionFactory;
use Enqueue\Null\NullMessage;
use Interop\Queue\Message as QueueMessage;
use Interop\Queue\Processor as InteropProcessor;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Throwable;

/**
 * Subprocess job runner command.
 * Executes a single job in an isolated subprocess.
 */
class SubprocessJobRunnerCommand extends Command
{
    /**
     * @param \Cake\Core\ContainerInterface|null $container DI container instance
     */
    public function __construct(
        protected readonly ?ContainerInterface $container = null,
    ) {
    }

    /**
     * Get the command name.
     *
     * @return string
     */
    public static function defaultName(): string
    {
        return 'queue subprocess-runner';
    }

    /**
     * Execute a single job from STDIN and output result to STDOUT.
     *
     * @param \Cake\Console\Arguments $args Arguments
     * @param \Cake\Console\ConsoleIo $io ConsoleIo
     * @return int
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $input = $this->readInput($io);

        if (empty($input)) {
            $this->outputResult($io, [
                'success' => false,
                'error' => 'No input received',
            ]);

            return self::CODE_ERROR;
        }

        $data = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->outputResult($io, [
                'success' => false,
                'error' => 'Invalid JSON input: ' . json_last_error_msg(),
            ]);

            return self::CODE_ERROR;
        }

        try {
            $result = $this->executeJob($data);
            $this->outputResult($io, [
                'success' => true,
                'result' => $result,
            ]);

            return self::CODE_SUCCESS;
        } catch (Throwable $throwable) {
            $this->outputResult($io, [
                'success' => false,
                'result' => InteropProcessor::REQUEUE,
                'exception' => [
                    'class' => get_class($throwable),
                    'message' => $throwable->getMessage(),
                    'code' => $throwable->getCode(),
                    'file' => $throwable->getFile(),
                    'line' => $throwable->getLine(),
                    'trace' => $throwable->getTraceAsString(),
                ],
            ]);

            return self::CODE_SUCCESS;
        }
    }

    /**
     * Read input from STDIN or ConsoleIo
     *
     * @param \Cake\Console\ConsoleIo $io ConsoleIo
     * @return string
     */
    protected function readInput(ConsoleIo $io): string
    {
        $input = '';
        while (!feof(STDIN)) {
            $chunk = fread(STDIN, 8192);
            if ($chunk === false) {
                break;
            }

            $input .= $chunk;
        }

        return $input;
    }

    /**
     * Execute the job with the provided data.
     *
     * @param array<string, mixed> $data Job data
     * @return string
     */
    protected function executeJob(array $data): string
    {
        $connectionFactory = new NullConnectionFactory();
        $context = $connectionFactory->createContext();

        $messageClass = $data['messageClass'] ?? NullMessage::class;

        // Validate message class for security
        if (!class_exists($messageClass) || !is_subclass_of($messageClass, QueueMessage::class)) {
            throw new RuntimeException(sprintf('Invalid message class: %s', $messageClass));
        }

        $messageBody = json_encode($data['body']);

        /** @var \Interop\Queue\Message $queueMessage */
        $queueMessage = new $messageClass($messageBody);

        if (isset($data['properties']) && is_array($data['properties'])) {
            foreach ($data['properties'] as $key => $value) {
                $queueMessage->setProperty($key, $value);
            }
        }

        $logger = $this->configureLogging($data);

        $message = new Message($queueMessage, $context, $this->container);
        $processor = new Processor($logger, $this->container);

        $result = $processor->processMessage($message);

        // Result is string|object (with __toString)
        /** @phpstan-ignore cast.string */
        return is_string($result) ? $result : (string)$result;
    }

    /**
     * Configure logging to use STDERR to prevent job logs from contaminating STDOUT.
     * Reconfigures all CakePHP loggers to write to STDERR with no additional formatting.
     *
     * @param array<string, mixed> $data Job data
     * @return \Psr\Log\LoggerInterface
     */
    protected function configureLogging(array $data): LoggerInterface
    {
        // Drop all existing loggers to prevent duplicate logging
        foreach (Log::configured() as $loggerName) {
            Log::drop($loggerName);
        }

        // Configure a single stderr logger
        Log::setConfig('default', [
            'className' => ConsoleLog::class,
            'stream' => 'php://stderr',
        ]);

        $logger = Log::engine('default');
        if (!$logger instanceof LoggerInterface) {
            $logger = new NullLogger();
        }

        return $logger;
    }

    /**
     * Output result as JSON to STDOUT.
     *
     * @param \Cake\Console\ConsoleIo $io ConsoleIo
     * @param array<string, mixed> $result Result data
     * @return void
     */
    protected function outputResult(ConsoleIo $io, array $result): void
    {
        $json = json_encode($result);
        if ($json !== false) {
            $io->out($json);
        }
    }
}
