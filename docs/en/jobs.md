# Defining and Queueing Jobs

## Defining Jobs

Jobs are classes that implement `Cake\Queue\Job\JobInterface`. They receive a `Cake\Queue\Job\Message` instance and return one of the `Interop\Queue\Processor` status constants.

```php
<?php
declare(strict_types=1);

namespace App\Job;

use Cake\Log\LogTrait;
use Cake\Queue\Job\JobInterface;
use Cake\Queue\Job\Message;
use Interop\Queue\Processor;

class ExampleJob implements JobInterface
{
    use LogTrait;

    public static $maxAttempts = 3;

    public static $shouldBeUnique = false;

    public function execute(Message $message): ?string
    {
        $id = $message->getArgument('id');
        $data = $message->getArgument('data');

        $this->log(sprintf('%d %s', $id, $data));

        return Processor::ACK;
    }
}
```

Job classes can use constructor injection in the same way as controllers or commands.

## Message Access

The `Message` object exposes:

- `getArgument($key = null, $default = null)` to fetch the full payload or a nested value using `Hash::get()` notation.
- `getContext()` to access the original queue context.
- `getOriginalMessage()` to access the broker-specific message object.
- `getParsedBody()` to inspect the parsed queue body.

## Return Values

A job may return:

- `Processor::ACK` when processing succeeded and the message should be removed.
- `Processor::REJECT` when processing failed permanently and the message should be removed.
- `Processor::REQUEUE` when the job should be retried later.
- `null`, which is treated as `Processor::ACK`.

Returning any other value is treated as a failure and results in the message being requeued.

## Job Properties

- `maxAttempts` limits how many times a job can be retried after an exception or explicit `Processor::REQUEUE`. If unset, the worker's `--max-attempts` option applies. If neither is set, retries are unlimited.
- `shouldBeUnique` allows only one queued copy of the same job class, method, and payload. Duplicate pushes are ignored. This requires `uniqueCache` in the queue configuration. When the payload is a DTO, its class is also factored into the uniqueness check, so two different DTO classes with coincidentally identical data are never treated as duplicates of each other.

## Queueing Jobs

Use `Cake\Queue\QueueManager` to publish a job:

```php
use App\Job\ExampleJob;
use Cake\Queue\QueueManager;

$data = ['id' => 7, 'is_premium' => true];
$options = ['config' => 'default'];

QueueManager::push(ExampleJob::class, $data, $options);
```

Arguments:

- The first argument is the job class name.
- The second argument is an optional JSON-serializable payload array.
- The third argument is an optional array of queueing options.

Supported options:

- `config`: queue config name. Defaults to `default`.
- `delay`: delay processing by a number of seconds. Broker support varies.
- `expires`: expire the message after a number of seconds if it has not been consumed.
- `priority`: one of `\Enqueue\Client\MessagePriority::VERY_LOW`, `LOW`, `NORMAL`, `HIGH`, or `VERY_HIGH`.
- `queue`: queue name to use. Defaults to the configured queue, then `default`.

## Dispatching and Receiving DTOs

Instead of an array, `QueueManager::push()` also accepts a DTO object as the payload:

```php
use App\Dto\OrderDto;
use App\Job\ProcessOrderJob;
use Cake\Queue\QueueManager;

$order = new OrderDto(id: 7, customer: 'Acme Corp');

QueueManager::push(ProcessOrderJob::class, $order);
```

The DTO is serialized into the same JSON-safe array that a plain array payload would produce (via `jsonSerialize()` when the DTO implements `JsonSerializable`, otherwise its public properties), and the DTO's class name travels alongside it so the job can hydrate it back. If you only have an array at the dispatch site but still want the job to receive a typed object, pass the target class via the `dtoClass` option instead:

```php
QueueManager::push(ProcessOrderJob::class, $data, [
    'dtoClass' => OrderDto::class,
]);
```

A plain array push with no `dtoClass` option behaves exactly as before; the message body is unchanged.

### Receiving a DTO in a job

Call `Message::getDto()` to hydrate the payload back into the DTO class it was dispatched with. `getArgument()` keeps returning the raw array, so existing jobs that only read array data are unaffected:

```php
public function execute(Message $message): ?string
{
    $order = $message->getDto(); // OrderDto, or null if no DTO was dispatched
    $id = $message->getArgument('id'); // the raw array is still available

    return Processor::ACK;
}
```

`getDto()` returns `null` when the message wasn't dispatched with a DTO, and also when the recorded `dtoClass` can no longer be autoloaded (e.g. the class was renamed or removed after the job was queued) — a job can always fall back to `getArgument()` in that case instead of crashing.

### Supported DTO classes

Hydration mirrors the DTO conventions used elsewhere in CakePHP (`#[RequestToDto]` for controllers, `SelectQuery::projectAs()` for the ORM), so the same DTO class can be reused across all three:

- **Constructor reflection** — a plain class (typically `readonly`) with typed, named constructor parameters. Nested DTOs are resolved from the parameter's type hint, and arrays of DTOs via the `#[CollectionOf]` attribute:

  ```php
  use Cake\ORM\Attribute\CollectionOf;

  readonly class OrderDto
  {
      /**
       * @param array<int, \App\Dto\OrderItemDto> $items
       */
      public function __construct(
          public int $id,
          public string $customer,
          #[CollectionOf(OrderItemDto::class)]
          public array $items = [],
      ) {
      }
  }
  ```

- **`createFromArray()` factory** — if the DTO class defines a static `createFromArray(array $data, bool $nested = false): static` method, it's used instead of reflection.
