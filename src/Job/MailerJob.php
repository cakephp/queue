<?php
declare(strict_types=1);

namespace Queue\Job;

use BadMethodCallException;
use Cake\Mailer\Email;
use Cake\Mailer\MailerAwareTrait;
use Interop\Queue\Processor;

class MailerJob implements JobInterface
{
    use MailerAwareTrait;

    /**
     * Constructs and dispatches the event from a job message
     *
     * @param \Queue\Job\Message $message job message
     * @return string
     */
    public function execute(Message $message): ?string
    {
        $mailerName = $message->getArgument('mailerName');
        $emailClass = $message->getArgument('emailClass', Email::class);
        $action = $message->getArgument('action');
        $args = $message->getArgument('args', []);
        $headers = $message->getArgument('headers', []);

        if (!class_exists($emailClass)) {
            return Processor::REJECT;
        }

        $mailer = null;
        $email = new $emailClass();
        try {
            $mailer = $this->getMailer($mailerName, $email);
        } catch (MissingMailerException $e) {
            return Processor::REJECT;
        }

        if ($mailer == null) {
            return Processor::REJECT;
        }

        try {
            $result = $mailer->send($action, $args, $headers);
        } catch (BadMethodCallException $e) {
            return Processor::REJECT;
        }

        return Processor::ACK;
    }
}
