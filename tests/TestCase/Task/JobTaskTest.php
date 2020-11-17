<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link http://cakephp.org CakePHP(tm) Project
 * @since 0.1.0
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Queue\Test\TestCase\Task;

use Cake\Command\Command;
use Cake\Queue\QueueManager;
use Cake\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\StringCompareTrait;
use Cake\TestSuite\TestCase;

/**
 * JobTask test class
 */
class JobTaskTest extends TestCase
{
    use ConsoleIntegrationTestTrait;
    use StringCompareTrait;

    /**
     * @var string
     */
    protected $generatedFile = '';

    /**
     * setup method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->comparisonDir = dirname(dirname(__DIR__)) . DS . 'comparisons' . DS;
        $this->useCommandRunner();
        QueueManager::drop('default');
    }

    public function tearDown(): void
    {
        parent::tearDown();

        if ($this->generatedFile && file_exists($this->generatedFile)) {
            unlink($this->generatedFile);
            $this->generatedFile = '';
        }
    }

    public function testMain()
    {
        $this->generatedFile = APP . 'Job/UploadJob.php';

        $this->exec('bake job upload');
        $this->assertExitCode(Command::CODE_SUCCESS);
        $this->assertFileExists($this->generatedFile);
        $this->assertOutputContains('Creating file ' . $this->generatedFile);
        $this->assertSameAsFile(
            $this->comparisonDir . 'JobTask.php',
            file_get_contents($this->generatedFile)
        );
    }
}
