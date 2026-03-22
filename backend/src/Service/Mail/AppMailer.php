<?php

namespace App\Service\Mail;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * Versand über {@see MailTransportResolver} (JSON-SMTP oder MAILER_DSN).
 */
final class AppMailer implements MailerInterface
{
    public function __construct(
        private MailTransportResolver $transportResolver
    ) {
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        $mailer = new Mailer($this->transportResolver->getTransport());
        $mailer->send($message, $envelope);
    }
}
