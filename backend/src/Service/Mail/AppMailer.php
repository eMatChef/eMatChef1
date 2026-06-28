<?php

namespace App\Service\Mail;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * Versand über {@see MailTransportResolver}; optional globales Reply-To; Versandprotokoll.
 */
final class AppMailer implements MailerInterface
{
    public function __construct(
        private MailTransportResolver $transportResolver,
        private MailOutboundSettingsStore $mailOutboundSettingsStore,
        private MailSendLogStore $mailSendLog,
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

            $kind = MailLogKind::read($message);
            $to = MailLogKind::firstAddress($message, 'to');
            $from = MailLogKind::firstAddress($message, 'from');
            $subject = (string) $message->getSubject();

            if ($message->getHeaders()->has(MailLogKind::HEADER)) {
                $message->getHeaders()->remove(MailLogKind::HEADER);
            }

            try {
                $mailer = new Mailer($this->transportResolver->getTransport());
                $mailer->send($message, $envelope);
                $this->mailSendLog->append($kind, $to, $subject, $from !== '' ? $from : null);
            } catch (\Throwable $e) {
                $detail = mb_substr(trim($e->getMessage()), 0, 160);
                $this->mailSendLog->append(
                    $kind . '.failed',
                    $to,
                    $detail !== '' ? $detail : 'Versand fehlgeschlagen',
                    $from !== '' ? $from : null
                );
                throw $e;
            }

            return;
        }

        $mailer = new Mailer($this->transportResolver->getTransport());
        $mailer->send($message, $envelope);
    }
}
