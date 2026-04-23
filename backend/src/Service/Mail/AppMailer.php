<?php

namespace App\Service\Mail;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * Versand über {@see MailTransportResolver}; optional globales Reply-To (siehe {@see MailOutboundSettingsStore}).
 */
final class AppMailer implements MailerInterface
{
    public function __construct(
        private MailTransportResolver $transportResolver,
        private MailOutboundSettingsStore $mailOutboundSettingsStore,
    ) {
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        if ($message instanceof Email) {
            $reply = $this->mailOutboundSettingsStore->getGlobalReplyToAddress();
            if ($reply !== null && $message->getReplyTo() === []) {
                $message = clone $message;
                $message->replyTo($reply);
            }
        }

        $mailer = new Mailer($this->transportResolver->getTransport());
        $mailer->send($message, $envelope);
    }
}
