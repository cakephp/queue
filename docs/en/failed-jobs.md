# Failed Jobs

By default, jobs that throw exceptions are requeued indefinitely. A job is treated as failed only when it returns `Processor::REQUEUE` or throws an exception and no retry attempts remain.

Retry limits can come from:

- The job class property `public static $maxAttempts`.
- The worker command option `--max-attempts`.

If `storeFailedJobs` is enabled, failed jobs are rejected, written to the `queue_failed_jobs` table, and can later be requeued manually. The original `config`, `queue`, and `priority` options are preserved.

Your broker may also support dead-letter queues. Those are separate transport-level features. Queue's failed-job storage is transport-agnostic and only tracks jobs that exhaust Queue's retry handling.

## Requeue Failed Jobs

Push failed jobs back onto the queue:

```bash
bin/cake queue requeue
```

Filters:

- Positional `ids` argument: comma-separated failed job IDs.
- `--class`: filter by job class.
- `--queue`: filter by queue name.
- `--config`: filter by queue config.
- `--force` or `-f`: skip the confirmation prompt.

If no filters are provided, all stored failed jobs are requeued.

## Purge Failed Jobs

Delete failed jobs from the database:

```bash
bin/cake queue purge_failed
```

Filters:

- Positional `ids` argument: comma-separated failed job IDs.
- `--class`: filter by job class.
- `--queue`: filter by queue name.
- `--config`: filter by queue config.
- `--force` or `-f`: skip the confirmation prompt.

If no filters are provided, all stored failed jobs are deleted.
