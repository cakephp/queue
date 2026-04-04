# Queue

The Queue plugin provides an easy-to-use interface for the [php-enqueue](https://php-enqueue.github.io) project, which abstracts a wide range of queuing backends for CakePHP applications. Use it to move long-running work such as email delivery, notifications, or report generation out of the request cycle.

Queue jobs are regular PHP classes. They can receive constructor dependencies from your application's container and are processed by the included `queue worker` command.

## Documentation Map

- [Installation and Configuration](/installation) covers plugin setup, transport configuration, and failed-job storage.
- [Defining and Queueing Jobs](/jobs) explains job classes, message handling, unique jobs, and `QueueManager::push()`.
- [Queueing Mail](/mail) shows how to queue mailer actions or send mail through the queue transport.
- [Custom Processors](/processors) explains how to replace the default message processor.
- [Running Workers](/workers) documents the worker command and its options.
- [Failed Jobs](/failed-jobs) covers storing, requeueing, and purging failed jobs.
- [Worker Events](/events) lists the events emitted while jobs are processed.
