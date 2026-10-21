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
     * @return int
     */
    public function execute(): int
    {
        /** @var \Cake\Queue\Model\Table\FailedJobsTable $failedJobsTable */
        $failedJobsTable = $this->getTableLocator()->get('Cake/Queue.FailedJobs');
        $jobsToRequeueQuery = $failedJobsTable->find();
        if ($this->args->hasArgument('ids')) {
            $idsArg = $this->args->getArgument('ids');

            if ($idsArg !== null) {
                $ids = explode(',', $idsArg);

                $jobsToRequeueQuery->whereInList('id', $ids);
            }
        }

        if ($this->args->hasOption('class')) {
            $jobsToRequeueQuery->where(['class' => $this->args->getOption('class')]);
        }

        if ($this->args->hasOption('queue')) {
            $jobsToRequeueQuery->where(['queue' => $this->args->getOption('queue')]);
        }

        if ($this->args->hasOption('config')) {
            $jobsToRequeueQuery->where(['config' => $this->args->getOption('config')]);
        }

        $requeueingCount = $jobsToRequeueQuery->count();
        if (!$requeueingCount) {
            $this->io->out('0 jobs found.');

            return self::CODE_SUCCESS;
        }

        if (!$this->args->getOption('force')) {
            $confirmed = $this->io->askChoice(sprintf('Requeue %s jobs?', $requeueingCount), ['y', 'n'], 'n');

            if ($confirmed !== 'y') {
                return self::CODE_SUCCESS;
            }
        }

        $this->io->out(sprintf('Requeueing %s jobs.', $requeueingCount));
        $succeededCount = 0;
        $failedCount = 0;
        /** @var array<\Cake\Queue\Model\Entity\FailedJob> $jobsToRequeue */
        $jobsToRequeue = $jobsToRequeueQuery->all();
        foreach ($jobsToRequeue as $failedJob) {
            $this->io->verbose(sprintf('Requeueing FailedJob with ID %d.', $failedJob->id));
            try {
                QueueManager::push(
                    [$failedJob->class, $failedJob->method],
                    $failedJob->data,
                    [
                        'config' => $failedJob->config,
                        'priority' => $failedJob->priority,
                        'queue' => $failedJob->queue,
                        'dtoClass' => $failedJob->dto_class ?? null,
                        'metadata' => $failedJob->metadata ?? [],
                    ],
                );

                $failedJobsTable->deleteOrFail($failedJob);

                $succeededCount++;
            } catch (Exception $e) {
                $this->io->err('Exception occurred while requeueing FailedJob with ID ' . $failedJob->id);
                $this->io->err((string)$e);

                $failedCount++;
            }
        }

        if ($failedCount !== 0) {
            $this->io->err(sprintf('Failed to requeue %d jobs.', $failedCount));
        }

        if ($succeededCount !== 0) {
            $this->io->success($succeededCount . ' jobs requeued.');
        }

        return self::CODE_SUCCESS;
    }
}
