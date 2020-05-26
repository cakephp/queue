Queue
#####

The Queue plugin provides an easy-to-use interface for the `php-queue
<https://php-enqueue.github.io>`_ project, which abstracts dozens of queuing
backends for use within your application. Queues can be used to increase the
performance of your application by deferring long-running processes - such as
email or notification sending - until a later time.

Installation
============

You can install this plugin into your CakePHP application using `composer
<https://getcomposer.org>`_.

The recommended way to install composer packages is:

.. code-block:: bash

    composer require cakephp/queue

Install the transport you wish to use. For a list of available transports, see
`this page <https://php-enqueue.github.io/transport>`_. The example below is for
pure-php redis:

.. code-block:: bash

    composer require enqueue/redis predis/predis:^1

Ensure that the plugin is loaded in your ``src/Application.php`` file, within
the ``Application::bootstrap()`` function::

    $this->addPlugin('Cake/Queue');

Configuration
=============

The following configuration should be present in your **config/app.php**::

    $config = [
        'Queue' => [
            'default' => [
                  // A DSN for your configured backend. default: null
                  'url' => 'redis:',

                  // The queue that will be used for sending messages. default: default
                  // This can be overriden when queuing or processing messages
                  'queue' => 'default',

                  // The name of a configured logger, default: null
                  'logger' => 'stdout',

                  // The name of an event listener class to associate with the worker
                  'listener' => \App\Listener\WorkerListener::class,
            ]
        ]
    ];

The ``Queue`` config key can contain one or more queue configurations. Each of
these is used for interacting with a different queuing backend.

Usage
=====

Defining Jobs
-------------

Create a Job class::

    <?php
    // src/Job/ExampleJob.php
    namespace App\Job;

    use Cake\Log\LogTrait;
    use Cake\Queue\Job\Message;
    use Interop\Queue\Processor;

    class ExampleJob implements JobInterface
    {
        use LogTrait;

        public function execute(Message $message): ?string
        {
            $id = $message->getArgument('id');
            $data = $message->getArgument('data');

            $this->log(sprintf('%d %s', $id, $data));

            return Processor::ACK;
        }
    }

The passed ``Message`` object has the following methods:

- ``getArgument($key = null, $default = null)``: Can return the entire passed
  dataset or a value based on a ``Hash::get()`` notation key.
- ``getContext()``: Returns the original context object.
- ``getOriginalMessage()``: Returns the original queue message object.
- ``getParsedBody()``: Returns the parsed queue message body.

A job *may* return any of the following values:

- ``Processor::ACK``: Use this constant when the message is processed
  successfully. The message will be removed from the queue.
- ``Processor::REJECT``: Use this constant when the message could not be
  processed. The message will be removed from the queue.
- ``Processor::REQUEUE``: Use this constant when the message is not valid or
  could not be processed right now but we can try again later. The original
  message is removed from the queue but a copy is published to the queue again.

The job **may** also return a null value, which is interpreted as
``Processor::ACK``. Failure to respond with a valid type will result in an
interpreted message failure and requeue of the message.

Queueing
--------

Queue the messages using the included `Queue\QueueManager` class::

    use App\Job\ExampleJob;
    use Cake\Queue\QueueManager;

    $callable = [ExampleJob::class, 'execute'];
    $arguments = ['id' => 7, 'data' => 'hi2u'];
    $options = ['config' => 'default'];

    QueueManager::push($callable, $arguments, $options);

Arguments:

- ``$callable``: A callable that will be invoked. This callable **must** be valid
  within the context of your application. Job classes are prefered.
- ``$arguments`` (optional): A json-serializable array of data that is to be
  made present for your message. It should be key-value pairs.
- ``$options`` (optional): An array of optional data for message queueing.

The following keys are valid for use within the ``options`` array:

- ``config``:

  - default: default
  - description: A queue config name
  - type: string

- ``delay``:

  - default: ``null``
  - description: Time - in integer seconds - to delay message, after which it will be processed. Not all message brokers accept this.
  - type: integer

- ``expires``:

  - default: ``null``
  - description: Time - in integer seconds - after which the message expires.
    The message will be removed from the queue if this time is exceeded and it
    has not been consumed.
  - type: integer

- ``priority``:

  - default: ``null``
  - type: constant
  - valid values:

    - ``\Enqueue\Client\MessagePriority::VERY_LOW``
    - ``\Enqueue\Client\MessagePriority::LOW``
    - ``\Enqueue\Client\MessagePriority::NORMAL``
    - ``\Enqueue\Client\MessagePriority::HIGH``
    - ``\Enqueue\Client\MessagePriority::VERY_HIGH``

- ``queue``:

  - default: from queue ``config`` array or string ``default`` if empty
  - description: The name of a queue to use
  - type: string

Queuing Mailer Actions
----------------------

Mailer actions can be queued by adding the ``Queue\Mailer\QueueTrait`` to the
mailer class. The following example shows how to setup the trait within a mailer
class::

    <?php
    namespace App\Mailer;

    use Cake\Mailer\Mailer;
    use Cake\Queue\Queue\QueueTrait;

    class UserMailer extends Mailer
    {
        use QueueTrait;

        public function welcome($emailAddress, $username)
        {
            $this
                ->setTo($emailAddress)
                ->setSubject(sprintf('Welcome %s', $username));
        }

        // ... other actions here ...
    }

It is now possible to use the ``UserMailer`` to send out user-related emails in
a delayed fashion from anywhere in our application. To queue the mailer action,
use the ``push()`` method on a mailer instance::

    $this->getMailer('User')->push('welcome', ['example@example.com', 'josegonzalez']);

This ``QueueTrait::push()`` call will generate an intermediate ``MailerJob``
that handles processing of the email message. If the MailerJob is unable to
instantiate the Email or Mailer instances, it is interpreted as
a ``Processor::REJECT``. An invalid ``action`` is also interpreted as
a ``Processor::REJECT``, as will the action throwing
a ``BadMethodCallException``. Any non-exception result will be seen as
a ``Processor:ACK``.

The exposed ``QueueTrait::push()`` method has a similar signature to
``Mailer::send()``, and also supports an ``$options`` array argument. The
options this array holds are the same options as those available for
``QueueManager::push()``, and additionally supports the following:

- ``emailClass``:
  - default: ``Cake\Mailer\Email::class``
  - description: The name of an email class to instantiate for use with the mailer
  - type: string

Run the worker
==============

Once a message is queued, you may run a worker via the included ``worker`` shell:

.. code-block:: bash

    bin/cake worker

This shell can take a few different options:

- ``--config`` (default: default): Name of a queue config to use
- ``--queue`` (default: default): Name of queue to bind to
- ``--logger`` (default: ``stdout``): Name of a configured logger
- ``--max-iterations`` (default: ``null``): Number of max iterations to run
- ``--max-runtime`` (default: ``null``): Seconds for max runtime

Worker Events
=============

The worker shell may invoke the events during normal execution. These events may
be listened to by the associated ``listener`` in the Queue config.

- ``Processor.message.exception``:
  - description: Dispatched when a message throws an exception.
  - arguments: ``message`` and ``exception``
- ``Processor.message.invalid``:
  - description: Dispatched when a message has an invalid callable.
  - arguments: ``message``
- ``Processor.message.reject``:
  - description: Dispatched when a message completes and is to be rejected.
  - arguments: ``message``
- ``Processor.message.success``:
  - description: Dispatched when a message completes and is to be acknowledged.
  - arguments: ``message``
- ``Processor.maxIterations``:
  - description: Dispatched when the max number of iterations is reached.
- ``Processor.maxRuntime``:
  - description: Dispatched when the max runtime is reached.
- ``Processor.message.failure``:
  - description: Dispatched when a message completes and is to be requeued.
  - arguments: ``message``
- ``Processor.message.seen``:
  - description: Dispatched when a message is seen.
  - arguments: ``message``
- ``Processor.message.start``:
  - description: Dispatched before a message is started.
  - arguments: ``message``
