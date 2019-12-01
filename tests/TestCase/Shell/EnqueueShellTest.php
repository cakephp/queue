<?php
namespace Queue\Test\TestCase\Shell;

use Cake\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Queue\Shell\EnqueueShell;

/**
 * Queue\Shell\EnqueueShell Test Case
 */
class EnqueueShellTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * ConsoleIo mock
     *
     * @var \Cake\Console\ConsoleIo|\PHPUnit_Framework_MockObject_MockObject
     */
    public $io;

    /**
     * Test subject
     *
     * @var \Queue\Shell\EnqueueShell
     */
    public $Enqueue;

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->io = $this->getMockBuilder('Cake\Console\ConsoleIo')->getMock();
        $this->Enqueue = new EnqueueShell($this->io);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->Enqueue);

        parent::tearDown();
    }

    /**
     * Test getOptionParser method
     *
     * @return void
     */
    public function testGetOptionParser()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test main method
     *
     * @return void
     */
    public function testMain()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
