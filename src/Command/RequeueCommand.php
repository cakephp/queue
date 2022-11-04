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
use Cake\Console\ConsoleOptionParser;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Queue\QueueManager;
use Exception;

class RequeueCommand extends Command
{
    use LocatorAwareTrait;

    /**
     * Get the command name.
     *
     * @return string
     */
    public static function defaultName(): string
    {
        return 'queue requeue';
    }

    /**
     * Gets the option parser instance and configures it.
     *
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function getOptionParser(): ConsoleOptionParser
    {
        $parser = parent::getOptionParser();

        $parser->setDescription('Requeue failed jobs.');

        $parser->addArgument('ids', [
            'required' => false,
            'help' => 'Requeue jobs by the FailedJob ID (comma-separated).',
        ]);
        $parser->addOption('class', [
            'help' => 'Requeue jobs by the job class.',
        ]);
        $parser->addOption('queue', [
            'help' => 'Requeue jobs by the queue the job was received on.',
        ]);
        $parser->addOption('config', [
            'help' => 'Requeue jobs by the config used to queue the job.',
        ]);
        $parser->addOption('force', [
            'help' => 'Automatically assume yes in response to confirmation prompt.',
            'short' => 'f',
            'boolean' => true,
        ]);

        return $parser;
    }

    /**
     * @param \Cake\Console\Arguments $args Arguments
     * @param \Cake\Console\ConsoleIo $io ConsoleIo
     * @return void
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        /** @var \Cake\Queue\Model\Table\FailedJobsTable $failedJobsTable */
        $failedJobsTable = $this->getTableLocator()->get('Cake/Queue.FailedJobs');

        $jobsToRequeue = $failedJobsTable->find();

        if ($args->hasArgument('ids')) {
            $idsArg = $args->getArgument('ids');

            if ($idsArg !== null) {
                $ids = explode(',', $idsArg);

                $jobsToRequeue->whereInList('id', $ids);
            }
        }

        if ($args->hasOption('class')) {
            $jobsToRequeue->where(['class' => $args->getOption('class')]);
        }

        if ($args->hasOption('queue')) {
            $jobsToRequeue->where(['queue' => $args->getOption('queue')]);
        }

        if ($args->hasOption('config')) {
            $jobsToRequeue->where(['config' => $args->getOption('config')]);
        }

        $requeueingCount = $jobsToRequeue->count();

        if (!$requeueingCount) {
            $io->out('0 jobs found.');

            return;
        }

        if (!$args->getOption('force')) {
            $confirmed = $io->askChoice("Requeue {$requeueingCount} jobs?", ['y', 'n'], 'n');

            if ($confirmed !== 'y') {
                return;
            }
        }

        $io->out("Requeueing {$requeueingCount} jobs.");

        $succeededCount = 0;
        $failedCount = 0;

        foreach ($jobsToRequeue as $failedJob) {
            $io->verbose("Requeueing FailedJob with ID {$failedJob->id}.");
            try {
                QueueManager::push(
                    [$failedJob->class, $failedJob->method],
                    $failedJob->decoded_data,
                    [
                        'config' => $failedJob->config,
                        'priority' => $failedJob->priority,
                        'queue' => $failedJob->queue,
                    ]
                );

                $failedJobsTable->deleteOrFail($failedJob);

                $succeededCount++;
            } catch (Exception $e) {
                $io->err("Exception occurred while requeueing FailedJob with ID {$failedJob->id}");
                $io->err((string)$e);

                $failedCount++;
            }
        }

        if ($failedCount) {
            $io->err("Failed to requeue {$failedCount} jobs.");
        }

        if ($succeededCount) {
            $io->success("{$succeededCount} jobs requeued.");
        }
    }
}
