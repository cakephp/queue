# Message Bus

The queue plugin ships a small message bus: an immutable `Cake\Queue\Bus\Envelope`
carrying the job payload plus typed *stamps*, and an ordered middleware chain on
both sides of the transport (dispatch and consume). Application code does not
change. `QueueManager::push()` and the `queue worker` command keep working as
before. The bus adds one extension point: **per-queue middleware**, which can
both enrich the envelope and alter the dispatch itself (routing, delay, priority).

## Dispatch middleware

Declare middleware per queue in the queue configuration:

```php
'Queue' => [
    'emails' => [
        'url' => 'redis://localhost:6379',
        'queue' => 'emails',
        'dispatchMiddleware' => [
            \App\Queue\TenantTagMiddleware::class, // class name, resolved from the container when available
            new \App\Queue\MetricsMiddleware($registry), // prebuilt instance
            static fn (?\Cake\Core\ContainerInterface $c) => new \App\Queue\SigningMiddleware($c), // factory
        ],
    ],
];
```

Each entry is a class name, a prebuilt instance implementing
`Cake\Queue\Bus\BusMiddlewareInterface`, or a closure factory receiving the
container (which may be `null`) and returning such an instance. Class names
resolve from the container when registered there, exactly like jobs, and are
instantiated directly otherwise. Prefer class names over closures in
`config/app.php`: closures do not survive config caching. Anything else
fails fast with an `InvalidArgumentException` when the bus is built.

Dispatch runs user middleware first, then the built-in unique check, then the
terminal sender. A middleware enriches the envelope for every downstream link,
so no core patches are needed:

```php
namespace App\Queue;

use Cake\Queue\Bus\BusMiddlewareInterface;
use Cake\Queue\Bus\BusStackInterface;
use Cake\Queue\Bus\Envelope;

final class TenantTagMiddleware implements BusMiddlewareInterface
{
    public function handle(Envelope $envelope, BusStackInterface $stack): Envelope
    {
        return $stack->next()->handle($envelope->withStamp(new TenantStamp('tenant:acme')), $stack);
    }
}
```

Returning the envelope without calling `$stack->next()` short-circuits the
pipeline: downstream middleware never runs and nothing is sent. Consumed
envelopes are never sent back to the transport: the sender refuses any envelope
carrying a `ReceivedStamp`, so a received message always flows toward handling.

## Altering the dispatch

The dispatch message is immutable, but it offers `withQueue()`, `withDelay()`,
`withExpires()` and `withPriority()` copiers, and the envelope offers
`withMessage()` to swap the message while keeping all stamps. Use them when the
sending decision depends on something the call site should not know.
`withMessage()` keeps stamps as is — on a consumed envelope that includes a
stale `JobMessageStamp`, so swap that stamp yourself if the new message needs
its own context:

```php
namespace App\Queue;

use Cake\Queue\Bus\BusMiddlewareInterface;
use Cake\Queue\Bus\BusStackInterface;
use Cake\Queue\Bus\Dispatch;
use Cake\Queue\Bus\Envelope;

final class TierRoutingMiddleware implements BusMiddlewareInterface
{
    public function handle(Envelope $envelope, BusStackInterface $stack): Envelope
    {
        $dispatch = $envelope->getMessage();
        if (!$dispatch instanceof Dispatch) {
            return $stack->next()->handle($envelope, $stack);
        }
        if ($this->tenantTier($envelope) !== 'premium') {
            return $stack->next()->handle($envelope, $stack);
        }

        return $stack->next()->handle(
            $envelope->withMessage($dispatch->withQueue('fast-lane')),
            $stack,
        );
    }
}
```

Routing, delay and priority stay declarative at the call site
(`QueueManager::push(..., ['queue' => ..., 'delay' => ...])`) and programmable
in middleware. Both write to the same `Dispatch`, which the sender reads when
it builds the transport message.

## Validating before send

A middleware can reject invalid payloads before they ever reach the broker.
The stamp carries the rules to apply, the middleware enforces them:

```php
namespace App\Queue;

use App\Queue\ValidationStamp; // app-defined stamp, same shape as TenantStamp below
use Cake\Queue\Bus\BusMiddlewareInterface;
use Cake\Queue\Bus\BusStackInterface;
use Cake\Queue\Bus\Dispatch;
use Cake\Queue\Bus\Envelope;
use Cake\Validation\Validator;

final class ValidationMiddleware implements BusMiddlewareInterface
{
    public function handle(Envelope $envelope, BusStackInterface $stack): Envelope
    {
        $stamp = $envelope->getLastStamp(ValidationStamp::class);
        $message = $envelope->getMessage();
        if ($stamp === null || !$message instanceof Dispatch) {
            return $stack->next()->handle($envelope, $stack);
        }

        $errors = (new Validator())
            ->requirePresence('userId')
            ->isInteger('userId')
            ->validate($message->getData());
        if ($errors !== []) {
            throw new \InvalidArgumentException('Invalid job payload: ' . json_encode($errors));
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
```

Throwing fails the `push()` call with a clear error. Returning the envelope
untouched short-circuits the pipeline instead, which `push()` reports with
a `LogicException` (see `SkippedAsDuplicateStamp` in the stamps reference
below).

## Consume middleware

The same `consumeMiddleware` key configures the consume side:

```php
'Queue' => [
    'emails' => [
        // ...
        'consumeMiddleware' => [\App\Queue\TenantScopeMiddleware::class],
    ],
],
```

Consume runs user middleware around the terminal handler, which resolves the
job callable and invokes it. A middleware needed in both phases can tell them
apart with the `ReceivedStamp` convention or the `PhaseEnum` built on it.
Dispatched envelopes do not carry the stamp, consumed envelopes do:

```php
use Cake\Queue\Bus\BusMiddlewareInterface;
use Cake\Queue\Bus\BusStackInterface;
use Cake\Queue\Bus\Envelope;
use Cake\Queue\Bus\PhaseEnum;

final class TenantScopeMiddleware implements BusMiddlewareInterface
{
    public function handle(Envelope $envelope, BusStackInterface $stack): Envelope
    {
        if (PhaseEnum::fromEnvelope($envelope) === PhaseEnum::Consume) {
            // set tenant scope from $envelope->getLastStamp(TenantStamp::class)
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
```

If a middleware needs to drop a message on consume (tenant filter, dedupe),
do it explicitly — a short-circuit without a terminal outcome resolves to
`REQUEUE`, bounded only by the worker retry limits:

```php
use Cake\Queue\Bus\BusMiddlewareInterface;
use Cake\Queue\Bus\BusStackInterface;
use Cake\Queue\Bus\Envelope;
use Cake\Queue\Bus\Stamp\HandledStamp;
use Interop\Queue\Processor;

final class TenantScopeMiddleware implements BusMiddlewareInterface
{
    public function handle(Envelope $envelope, BusStackInterface $stack): Envelope
    {
        if ($this->isForeignTenant($envelope)) {
            return $envelope->withStamp(new HandledStamp(Processor::ACK));
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
```

If a custom `processor` is configured, it runs inside the bus through a thin
adapter instead of `HandlerMiddleware`; the `Queue.listener` is attached to
the terminal's own event manager: `HandlerMiddleware` on the default path,
or the custom processor itself when it exposes `getEventManager` (processors
without one stay silent, as before the bus).

## Two rings: extensions vs middleware

Transport mechanics (retry clone, redelivery delay, unique-job release,
failed-job row) stay Enqueue extensions **outside** the bus, chained as the
`ChainExtension` around `consume()`. They own transport mechanics because only
they receive the transport `Consumer`, which `InteropProcessor::process()`
never gets. Bus middlewares run **inside** that chain and own envelopes and
handler invocation. The layers do not collapse into each other by design.

## Stamps and the `stampTypes` registry

Only plain arrays travel. Each stamp serializes with `toArray()` (a short
string `type`, never a FQCN, plus a per-stamp `v`) and rebuilds with
`fromArray()`; corrupt rows are skipped so a poison stamp never kills a message
whose `data` is fine. Unknown `type` values are skipped as well, which keeps
new readers and old writers interoperable.

Core ships only machinery stamps (`ReceivedStamp`, `HandledStamp`,
`JobMessageStamp`, `SentStamp`, `SkippedAsDuplicateStamp`) that its own
transport code produces or reads.
Everything domain-specific is an app stamp. Register the mapping per queue:

```php
'Queue' => [
    'emails' => [
        // ...
        'stampTypes' => [
            'TenantStamp' => \App\Queue\TenantStamp::class,
            'ValidationStamp' => \App\Queue\ValidationStamp::class,
        ],
    ],
];
```

```php
namespace App\Queue;

use Cake\Queue\Bus\StampInterface;

final class TenantStamp implements StampInterface
{
    public function __construct(private readonly string $tenant)
    {
    }

    public function getTenant(): string
    {
        return $this->tenant;
    }

    public function toArray(): array
    {
        return ['type' => 'TenantStamp', 'v' => 1, 'tenant' => $this->tenant];
    }

    public static function fromArray(array $data): static
    {
        if (!isset($data['tenant']) || !is_string($data['tenant']) || $data['tenant'] === '') {
            throw new \InvalidArgumentException('TenantStamp requires a non-empty string `tenant`.');
        }

        return new static($data['tenant']);
    }
}
```

Unknown `type` values are skipped, while misconfiguration fails fast: a
non-array map, an unknown class, a class not implementing `StampInterface`,
or a collision with a core type name throws an `InvalidArgumentException`
when the bus is built. Stamps implementing `NonSendableStampInterface` are
never written to the transport and never created from it. Stamps that carry
only identifiers travel; the payload itself is never duplicated into stamps.

## Observability

`Cake\Queue\Bus\Middleware\LoggingMiddleware` is a shipped example of a
stamp-free middleware: it logs the phase and payload type of every envelope.
Use it as the template for your own cross-cutting middleware (metrics,
envelope validation):

```php
'Queue' => [
    'emails' => [
        // ...
        'dispatchMiddleware' => [\Cake\Queue\Bus\Middleware\LoggingMiddleware::class],
        'consumeMiddleware' => [\Cake\Queue\Bus\Middleware\LoggingMiddleware::class],
    ],
],
```

## Failed jobs

Envelopes that exhaust their retries land in `queue_failed_jobs` with the
`metadata` column copied whole, stamps included. No migration is needed to
keep transport bookkeeping on failed rows.

## Shipped middleware reference

Dispatch pipeline, in order: user `dispatchMiddleware`, `UniqueCheckMiddleware`,
terminal `SendMiddleware`. Consume pipeline, in order: user `consumeMiddleware`,
terminal `HandlerMiddleware` (default) or `ProcessorAdapterMiddleware` (custom
`processor` config).

- `UniqueCheckMiddleware` (dispatch): drops duplicate dispatches of unique
  jobs before sending. On a duplicate the downstream pipeline never runs and
  the envelope is marked with `SkippedAsDuplicateStamp` instead of a
  `SentStamp`, so `QueueManager::push()` resolves to a skipped
  `DispatchResult`.
- `SendMiddleware` (dispatch terminal): assembles the transport body from
  the dispatch message plus the envelope stamps (`metadata.stamps`), applies
  delay/expiry/priority, and sends through `EnqueueClient` (which emits the
  `Queue.produce.success` / `Queue.produce.failure` events). Attaches a
  `SentStamp` with the transport message identity on success. Requires a
  `Dispatch` message (`RuntimeException` otherwise) and refuses envelopes
  carrying a `ReceivedStamp` (`LogicException`, re-send-loop guard).
- `HandlerMiddleware` (consume terminal, default path): requires a
  `JobMessageStamp`, resolves the job callable and invokes it. Unresolvable
  callables — unknown class, unbuildable job (including constructor
  failures) — are classified invalid and `REJECT`ed (dropped), not
  retried. Emits `Processor.message.seen` (`queueMessage`), `start`
  (`message`), `success` / `reject` / `failure` (`message`, `duration`),
  `exception` (`message`, `exception`, `duration`) and `invalid`
  (`message`), and wraps the outcome in a `HandledStamp`. A `null` job
  return becomes `ACK`; non-string, non-object returns are rejected as
  invalid; `ACK` / `REJECT` pass through and any other job result becomes
  `REQUEUE`. At the bus level, `ACK` / `REJECT` / `REQUEUE` /
  `ALREADY_ACKNOWLEDGED` and `Result` objects pass through, a missing
  outcome becomes `REQUEUE`, and anything else throws.
- `ProcessorAdapterMiddleware` (consume terminal, custom `processor` path):
  runs the custom processor with the original transport message and context
  and wraps the untouched result in a `HandledStamp`. Deliberately
  transparent: it emits no lifecycle events and writes no logs, so
  observability stays with the custom processor itself. The `Queue.listener`
  is attached to the custom processor when it exposes an event manager (as
  before the bus); processors without one stay silent, also as before.
  Custom processor exceptions bubble instead of degrading to requeue.
- `LoggingMiddleware` (reference example): stateless, stamp-free, works on
  both buses. Logs the pipeline phase (`PhaseEnum`, derived from the
  `ReceivedStamp`) and the payload type. Copy its shape for app middleware
  (tenant scoping, metrics, envelope validation).

## Core stamps reference

- `ReceivedStamp` (in-memory): attached locally by `BusProcessor` with the
  transport name on receipt; tells the dispatch phase apart from the consume
  phase (`PhaseEnum::fromEnvelope()`). Forged rows are dropped by the
  parser and never rebuilt. Envelopes carrying it are never re-sent
  (`SendMiddleware` refuses them).
- `SentStamp` (in-memory): added by `SendMiddleware` after publishing;
  read back by `QueueManager::push()` as the message identity of the
  returned `DispatchResult` (`null` when the transport assigns no identity).
- `SkippedAsDuplicateStamp` (in-memory): added by `UniqueCheckMiddleware`
  on duplicates; read back by `QueueManager::push()` as a skipped
  `DispatchResult`.
  A finished dispatch carrying neither this stamp nor a `SentStamp` means
  user middleware swallowed the dispatch, which `push()` reports with a
  `LogicException`.
- `HandledStamp` (in-memory): terminal result carrier, holding a status
  string or a `Result` object; mapped back to the transport contract by
  `BusProcessor` (`ACK` / `REJECT` / `ALREADY_ACKNOWLEDGED` pass through,
  missing outcome becomes `REQUEUE`, anything else throws).
- `JobMessageStamp` (in-memory): carries the live job message and queue
  context; attached by `BusProcessor` and required by both terminals. Never
  serialized: it holds no wire representation by design.
