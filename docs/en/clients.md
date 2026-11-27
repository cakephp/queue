# Custom Clients

Queue connections use `Cake\Queue\Client\EnqueueClient` by default. A connection can use a custom client class by setting the `clientClass` option in the queue configuration. The class must extend `Cake\Queue\Client\EnqueueClient`.

```php
'Queue' => [
    'default' => [
        'url' => 'null:',
        'queue' => 'default',
    ],
    'audited' => [
        'url' => 'redis://localhost:6379',
        'queue' => 'audited',
        'clientClass' => \App\Queue\AuditedClient::class,
    ],
],
```

If no client class is configured, the plugin uses `Cake\Queue\Client\EnqueueClient`.

## Configuration

`EnqueueClient` is built from the `url` value without any Symfony configuration processing. The `url` value can be a DSN string or an array with `transport`, `client` and `extensions` sections. Extra transport keys are passed to the connection factory. Use `connection_factory_class` inside the `transport` section to provide a custom `Interop\Queue\ConnectionFactory`.

## Produce Events

The client dispatches two events around every produce operation. Subscribe to these events on the client event manager to observe publishing.

- `Queue.produce.success` is dispatched after the transport accepts the message. Payload keys: `topic` or `command`, `messageId`, `transportDestination`. For commands the payload also includes `promise`.
- `Queue.produce.failure` is dispatched when the transport throws. Payload keys: `topic` or `command`, `exception`. The original exception is rethrown after dispatch.

Produce events carry identifiers and routing metadata only, never message bodies. The sender already holds the message object, while events are broadcast to every listener, so bodies are deliberately excluded. Event payloads are safe to log.

The `messageId` value is assigned by the transport during `sendEvent` or `sendCommand`. The client only observes it through the post send extension and never generates it. `QueueManager::push()` returns a `DispatchResult`: `messageId` on success (`null` when the transport assigns none) and `skippedAsDuplicate` when a unique push is skipped as duplicate.

Direct calls on `$client->getProducer()->sendEvent()` or `sendCommand()` bypass these events. Only `EnqueueClient::sendEvent()`, `EnqueueClient::sendCommand()` and `QueueManager::push()` dispatch produce events.

When binding processors, always pass an explicit `$processorName`. Names generated with `uniqid()` are a fallback for tests and prototypes, not a naming scheme.

Custom client classes with a non-standard producer must assign a message id on the passed message before returning, otherwise `QueueManager::push()` reports a `null` message id on success. The outcome stays unambiguous through `DispatchResult::skippedAsDuplicate`.

## Example

```php
<?php
declare(strict_types=1);

namespace App\Queue;

use Cake\Queue\Client\EnqueueClient;

class AuditedClient extends EnqueueClient
{
    protected function createConnection(array $transport): \Interop\Queue\ConnectionFactory
    {
        return parent::createConnection($transport);
    }
}
```

Attach a listener in application code:

```php
$engine = \Cake\Queue\QueueManager::engine('audited');
$engine->getEventManager()->on('Queue.produce.success', function ($event) {
    $messageId = $event->getData('messageId');
});
```

The worker side keeps its own event set. See Worker Events for processing events.
