# Upgrading to 3.0

Queue 3.0 requires PHP 8.2 or later and CakePHP 5.4 or later.

## Deprecated `Cake\Queue\Queue\Processor` base class

The `Cake\Queue\Queue\Processor` base class no longer drives the worker.
The worker now consumes through a message bus: without a custom `processor`
config it runs the consume pipeline with `HandlerMiddleware` as the
terminal, wrapped in a bus-backed `BusProcessor` that
`WorkerCommand::getProcessor()` returns as the default processor. Custom
processors keep working through an adapter and the `processor` config key
is unchanged.

For existing hierarchies the class is kept as a deprecated thin shell over
the shared `Cake\Queue\Job\JobExecutionTrait`: `extends Processor`,
`processMessage()` overrides, the `(LoggerInterface, ContainerInterface)`
constructor, `getEventManager()` listeners and `'processor' =>
Processor::class` configs keep working as before. New code should not extend
it: omit the `processor` key for the bus-native pipeline, or implement
`Interop\Queue\Processor` directly (see Custom Processors).

Migrate a custom processor step by step:

1. Drop the `extends`: implement `Interop\Queue\Processor` directly (see
   Custom Processors for a full example).
2. Keep the `__construct(LoggerInterface, ContainerInterface)` signature
   (use `Cake\Core\ContainerInterface`); the worker resolves the
   processor from the container when registered there, and otherwise
   instantiates it directly with those two arguments.
3. Replace `Processor::ACK` / `Processor::REJECT` constants with
   `Interop\Queue\Processor::ACK` / `Interop\Queue\Processor::REJECT`.
4. Move `Processor.message.*` listeners to the `Queue.<config>.listener`
   setting: events now fire from the bus terminal (`HandlerMiddleware` on
   the default path) or from the custom processor itself when it exposes
   an event manager (custom path — the adapter is transparent and only the
   processor's own manager receives the listener; processors without one
   stay silent). The event names and payloads are unchanged.
5. Job-execution helpers (`getCallable()`) live on `Cake\Queue\Job\Message`;
   the shared `Cake\Queue\Job\JobExecutionTrait` carries the whole run flow
   (events, `null` → `ACK` mapping, exception → requeue), so processors that
   only ran jobs can drop their override entirely and omit the `processor`
   key — or delegate to the trait (see Custom Processors).
6. Statuses mostly keep the transport behavior: on the default path a
   `null` job return becomes `ACK`, `ACK` / `REJECT` pass through, and
   anything else becomes `REQUEUE` — as before. One deliberate change:
   non-string, non-object job returns (e.g. `bool`) used to crash with a
   `TypeError` that degraded into a requeue loop; they are now rejected as
   invalid. At the bus level, `ACK` / `REJECT` / `REQUEUE` /
   `ALREADY_ACKNOWLEDGED` and `Result` objects pass through, a missing
   outcome becomes `REQUEUE`, and anything else throws
   (`LogicException('Status is not supported: ...')`, as the old direct
   binding did via the transport consumer). A custom processor returning
   a status the transport does not support fails loudly. Exceptions from
   custom processors bubble instead of becoming quiet requeues.

Legacy `extends Processor` hierarchies keep working through a transparent
adapter: lifecycle events and debug lines come from the processor itself,
exactly as without the bus, and listeners attached via
`Queue.<config>.listener` hear that set exactly once (the listener is
attached to the custom processor's own event manager, as before the bus).
Do not write new hierarchies: omit the `processor` key for the bus-native
pipeline, or implement `Interop\Queue\Processor` directly (see Custom
Processors).

## `QueueManager::push()` Returns `DispatchResult`

`push()` used to return the transport message id (`?string`, `null` for
duplicates and id-less transports). It now returns a
`Cake\Queue\Bus\DispatchResult`: `messageId` (still `null` when the
transport assigns none) plus a `skippedAsDuplicate` flag with `isSent()` /
`isSkipped()` helpers. Code that branched on `null` must switch to the flag:
because the result is now always an object, old checks like
`$id === null`, `empty($id)` or string interpolation silently change meaning
(an object is always truthy and never `=== null`) instead of failing loudly.

```php
$result = QueueManager::push(MyJob::class, $data);

// Before: $id === null meant "duplicate or id-less success".
// After:
if ($result->isSkipped()) {
    // duplicate unique job, nothing was sent
}

$messageId = $result->messageId;
```

## Removed Deprecated Plugin Class

`Cake\Queue\Plugin` has been removed. Load the plugin with
`Cake\Queue\QueuePlugin` instead:

```php
$this->addPlugin('Cake/Queue');
```

## Failed-Job `data` Is Now a Native JSON Column

The `queue_failed_jobs.data` column (and the new `metadata` column, see Failed Jobs) uses the native `json` type, so the ORM returns arrays directly: `$failedJob->data` is an array, not a JSON string. The `FailedJob::decoded_data` and `FailedJob::decoded_metadata` accessors were removed — read `$failedJob->data` and `$failedJob->metadata` instead. Run the plugin migrations to get the new schema:

```bash
bin/cake migrations migrate --plugin Cake/Queue
```
