# Worker Events

Workers dispatch events during normal message processing. If your queue config defines a `listener`, that class can subscribe to these events and react to worker activity.

## Available Events

- `Processor.message.exception`: dispatched when a message throws an exception. Arguments: `message`, `exception`.
- `Processor.message.invalid`: dispatched when a message contains an invalid callable. Arguments: `message`.
- `Processor.message.reject`: dispatched when a message finishes with `Processor::REJECT`. Arguments: `message`.
- `Processor.message.success`: dispatched when a message finishes with `Processor::ACK`. Arguments: `message`.
- `Processor.message.failure`: dispatched when a message finishes with `Processor::REQUEUE`. Arguments: `message`.
- `Processor.message.seen`: dispatched when a message is first observed by the worker. Arguments: `message`.
- `Processor.message.start`: dispatched immediately before processing begins. Arguments: `message`.
