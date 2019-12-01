<?php
namespace Queue\Shell;

use Cake\Console\Shell;
use Cake\Core\Configure;
use Cake\Log\Log;
use Cake\Utility\Hash;
use Enqueue\SimpleClient\SimpleClient;
use Interop\Queue\Message;
use Queue\Queue\Processor;
use Queue\Queue\QueueExtension;

/**
 * Worker shell command.
 */
class WorkerShell extends Shell
{
    /**
     * Gets the option parser instance and configures it.
     *
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function getOptionParser()
    {
        $parser = parent::getOptionParser();
        $parser->addOption('config', [
            'default' => 'default',
            'help' => 'Name of a queue config to use',
            'short' => 'c',
        ]);
        $parser->addOption('queue', [
            'default' => 'default',
            'help' => 'Name of queue to bind to',
            'short' => 'Q',
        ]);
        $parser->addOption('logger', [
            'help' => 'Name of a configured logger',
            'default' => 'stdout',
            'short' => 'l',
        ]);
        $parser->addOption('max-iterations', [
            'help' => 'Number of max iterations to run',
            'default' => null,
            'short' => 'i',
        ]);
        $parser->addOption('max-runtime', [
            'help' => 'Seconds for max runtime',
            'default' => null,
            'short' => 'r',
        ]);
        $parser->setDescription(__('Runs a Queuesadilla worker.'));

        return $parser;
    }

    protected function getQueueExtension()
    {
        $maxIterations = $this->params['max-iterations'];
        $maxRuntime = $this->params['max-runtime'];
        $extension = new QueueExtension($maxIterations, $maxRuntime)

    }

    /**
     * main() method.
     *
     * @return bool|int|null Success or error code.
     */
    public function main()
    {
        $config = Hash::get($this->params, 'config');
        $url = Configure::read(sprintf('Queue.%s.url', $config));
        $logger = Log::engine($this->params['logger']);

        $processor = new Processor();
        $extension = $this->getQueueExtension();

        if (!empty($config['listener'])) {
            if (!class_exists($config['listener'])) {
                throw new LogicException(sprintf('Listener class %s not found', $config['listener']));
            }

            $listener = new $config['listener'];
            $processor->getEventManager()->on($listener);
            $extension->getEventManager()->on($listener);
        }

        $client = new SimpleClient($url, $logger);
        $client->bindTopic($this->params['queue'], $processor);
        $client->consume($extension);
    }
}
