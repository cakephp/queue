<?php
declare(strict_types=1);

namespace TestApp;

use Cake\Log\Log;
use Cake\Mailer\Mailer;
use Cake\Queue\Mailer\QueueTrait;

class WelcomeMailer extends Mailer
{
    use QueueTrait;

    public function getName()
    {
    }

    public function welcome()
    {
        $debug = Log::engine('debug');
        $debug->info('Welcome mail sent');
    }
}
