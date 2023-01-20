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

class PurgeFailedCommand extends Command
{
    use LocatorAwareTrait;

    /**
     * Get the command name.
     *
     * @return string
     */
    public static function defaultName(): string
    {
        return 'queue purge_failed';
    }

    /**
     * Gets the option parser instance and configures it.
     *
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function getOptionParser(): ConsoleOptionParser
    {
        $parser = parent::getOptionParser();

        $parser->setDescription('Delete failed jobs.');

        $parser->addArgument('ids', [
            'required' => false,
            'help' => 'Delete jobs by the FailedJob ID (comma-separated).',
        ]);
        $parser->addOption('class', [
            'help' => 'Delete jobs by the job class.',
        ]);
        $parser->addOption('queue', [
            'help' => 'Delete jobs by the queue the job was received on.',
        ]);
        $parser->addOption('config', [
            'help' => 'Delete jobs by the config used to queue the job.',
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
    public function execute(Arguments $args, ConsoleIo $io): void
    {
        /** @var \Cake\Queue\Model\Table\FailedJobsTable $failedJobsTable */
        $failedJobsTable = $this->getTableLocator()->get('Cake/Queue.FailedJobs');

        $jobsToDelete = $failedJobsTable->find();

        if ($args->hasArgument('ids')) {
            $idsArg = $args->getArgument('ids');

            if ($idsArg !== null) {
                $ids = explode(',', $idsArg);

                $jobsToDelete->whereInList($failedJobsTable->aliasField('id'), $ids);
            }
        }

        if ($args->hasOption('class')) {
            $jobsToDelete->where(['class' => $args->getOption('class')]);
        }

        if ($args->hasOption('queue')) {
            $jobsToDelete->where(['queue' => $args->getOption('queue')]);
        }

        if ($args->hasOption('config')) {
            $jobsToDelete->where(['config' => $args->getOption('config')]);
        }

        $deletingCount = $jobsToDelete->count();

        if (!$deletingCount) {
            $io->out('0 jobs found.');

            return;
        }

        if (!$args->getOption('force')) {
            $confirmed = $io->askChoice("Delete {$deletingCount} jobs?", ['y', 'n'], 'n');

            if ($confirmed !== 'y') {
                return;
            }
        }

        $io->out("Deleting {$deletingCount} jobs.");

        $failedJobsTable->deleteManyOrFail($jobsToDelete);

        $io->success("{$deletingCount} jobs deleted.");
    }
}
