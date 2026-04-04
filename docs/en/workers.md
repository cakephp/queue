# Running Workers

Once jobs have been queued, start a worker with:

```bash
bin/cake queue worker
```

The plugin also registers `bin/cake worker` as an alias for the same command.

## Options

- `--config` or `-c`: queue config name. Defaults to `default`.
- `--queue` or `-Q`: queue name to bind to. Defaults to the queue defined by the selected config.
- `--processor` or `-p`: processor name used for Enqueue topic binding.
- `--logger` or `-l`: CakePHP logger name. Used when running with verbose output.
- `--max-jobs` or `-i`: stop after processing a number of jobs.
- `--max-runtime` or `-r`: stop after running for a number of seconds.
- `--max-attempts` or `-a`: maximum retry count for each job unless the job class overrides it.
- `--verbose` or `-v`: enable verbose output through the selected logger.

## How Workers Are Built

When a worker starts it:

- Loads the named queue config.
- Instantiates the configured processor class or falls back to `Cake\Queue\Queue\Processor`.
- Attaches retry limiting and optional failed-job persistence.
- Applies optional consumption limits such as max jobs and max runtime.
- Attaches the configured listener class, if any, to processor events.
- Binds the processor to the selected queue and consumes messages.
