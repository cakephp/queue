# Queueing Mail

## Queueing Mailer Actions

Add `Cake\Queue\Mailer\QueueTrait` to a mailer to make its actions queueable:

```php
<?php
declare(strict_types=1);

namespace App\Mailer;

use Cake\Mailer\Mailer;
use Cake\Queue\Mailer\QueueTrait;

class UserMailer extends Mailer
{
    use QueueTrait;

    public function welcome(string $emailAddress, string $username): void
    {
        $this
            ->setTo($emailAddress)
            ->setSubject(sprintf('Welcome %s', $username));
    }
}
```

Queue an action with `push()`:

```php
$this->getMailer('User')->push('welcome', ['example@example.com', 'josegonzalez']);
```

`QueueTrait::push()` creates an intermediate `MailerJob` that executes the mailer action later. Its signature mirrors `Mailer::send()` and also accepts the same queueing `$options` supported by `QueueManager::push()`.

If the mailer or email instance cannot be created, the action name is invalid, or the action throws `BadMethodCallException`, the message is rejected.

## Delivering Mail Through Queue Jobs

If you do not use mailers, configure `Cake\Queue\Mailer\Transport\QueueTransport` as an email transport:

```php
use Cake\Queue\Mailer\Transport\QueueTransport;

return [
    'EmailTransport' => [
        'default' => [
            'className' => MailTransport::class,
        ],
        'queue' => [
            'className' => QueueTransport::class,
            'transport' => 'default',
        ],
    ],
    'Email' => [
        'default' => [
            'transport' => 'queue',
        ],
    ],
];
```

With this configuration, sending mail with the `default` email profile creates a queue message. When that message is processed, the configured underlying transport sends the email.
