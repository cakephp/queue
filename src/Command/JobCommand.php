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

use Bake\Command\SimpleBakeCommand;
use Cake\Console\ConsoleOptionParser;

class JobCommand extends SimpleBakeCommand
{
    public $pathFragment = 'Job/';

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return 'job';
    }

    /**
     * @inheritDoc
     */
    public function fileName(string $name): string
    {
        return $name . 'Job.php';
    }

    /**
     * @inheritDoc
     */
    public function template(): string
    {
        return 'Cake/Queue.job';
    }

    /**
     * Gets the option parser instance and configures it.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to update.
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = $this->_setCommonOptions($parser);

        return $parser
            ->setDescription('Bake a queue job class.')
            ->addArgument('name', [
                'help' => 'The name of the queue job class to create.',
            ]);
    }
}
