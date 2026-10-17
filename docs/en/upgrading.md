# Upgrading to 3.0

Queue 3.0 requires PHP 8.2 or later and CakePHP 5.4 or later.

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
