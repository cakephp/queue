<?php
declare(strict_types=1);

namespace Queue\Job;

use BadMethodCallException;
use Cake\Mailer\Email;
use Cake\Mailer\Exception\MissingMailerException;
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
        $mailerConfig = $message->getArgument('mailerConfig');
        $action = $message->getArgument('action');
        $args = $message->getArgument('args', []);
        $headers = $message->getArgument('headers', []);

        try {
            $mailer = $this->getMailer($mailerName, $mailerConfig);
        } catch (MissingMailerException $e) {
            return Processor::REJECT;
        }

        try {
            $mailer->send($action, $args, $headers);
        } catch (BadMethodCallException $e) {
            return Processor::REJECT;
        }

        return Processor::ACK;
    }
}
