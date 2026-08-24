# Upgrading to 3.0

Queue 3.0 requires PHP 8.2 or later and CakePHP 5.4 or later.

## Removed Deprecated Plugin Class

`Cake\Queue\Plugin` has been removed. Load the plugin with
`Cake\Queue\QueuePlugin` instead:

```php
$this->addPlugin('Cake/Queue');
```
