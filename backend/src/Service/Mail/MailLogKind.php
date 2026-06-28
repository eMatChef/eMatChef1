<?php

declare(strict_types=1);

namespace App\Service\Mail;

use Symfony\Component\Mime\Email;

/**
 * Log-Art für {@see AppMailer} / mail_send_log.json (Header wird nicht mitversendet).
 */
final class MailLogKind
{
    public const HEADER = 'X-EmatChef-Mail-Kind';

    public const DEFAULT = 'mail.outbound';

    public static function stamp(Email $email, string $kind): Email
    {
        $email->getHeaders()->addTextHeader(self::HEADER, $kind);

        return $email;
    }

    public static function read(Email $email): string
    {
        $header = $email->getHeaders()->get(self::HEADER);
        if ($header === null) {
            return self::DEFAULT;
        }
        $kind = trim($header->getBodyAsString());
        if ($kind === '') {
            return self::DEFAULT;
        }

        return $kind;
    }

    public static function firstAddress(Email $email, string $method): string
    {
        $addresses = $email->{'get' . ucfirst($method)}();
        if ($addresses === []) {
            return '';
        }
        $first = $addresses[0];

        return $first->getAddress();
    }
}
