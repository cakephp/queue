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
     * @return int
     */
    public function execute(): int
    {
        /** @var \Cake\Queue\Model\Table\FailedJobsTable $failedJobsTable */
        $failedJobsTable = $this->getTableLocator()->get('Cake/Queue.FailedJobs');
        $jobsToDelete = $failedJobsTable->find();
        if ($this->args->hasArgument('ids')) {
            $idsArg = $this->args->getArgument('ids');

            if ($idsArg !== null) {
                $ids = explode(',', $idsArg);

                $jobsToDelete->whereInList($failedJobsTable->aliasField('id'), $ids);
            }
        }

        if ($this->args->hasOption('class')) {
            $jobsToDelete->where(['class' => $this->args->getOption('class')]);
        }

        if ($this->args->hasOption('queue')) {
            $jobsToDelete->where(['queue' => $this->args->getOption('queue')]);
        }

        if ($this->args->hasOption('config')) {
            $jobsToDelete->where(['config' => $this->args->getOption('config')]);
        }

        $deletingCount = $jobsToDelete->count();
        if (!$deletingCount) {
            $this->io->out('0 jobs found.');

            return self::CODE_SUCCESS;
        }

        if (!$this->args->getOption('force')) {
            $confirmed = $this->io->askChoice(sprintf('Delete %s jobs?', $deletingCount), ['y', 'n'], 'n');

            if ($confirmed !== 'y') {
                return self::CODE_SUCCESS;
            }
        }

        $this->io->out(sprintf('Deleting %s jobs.', $deletingCount));
        $failedJobsTable->deleteManyOrFail($jobsToDelete);
        $this->io->success($deletingCount . ' jobs deleted.');

        return self::CODE_SUCCESS;
    }
}
