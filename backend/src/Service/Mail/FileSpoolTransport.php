<?php

namespace App\Service\Mail;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * Schreibt ausgehende Mails als .eml-Dateien (Fallback ohne SMTP / MAILER_DSN).
 */
final class FileSpoolTransport extends AbstractTransport
{
    public function __construct(private string $directory)
    {
        parent::__construct();
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }
    }

    protected function doSend(SentMessage $message): void
    {
        $name = 'mail_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.eml';
        $path = $this->directory . DIRECTORY_SEPARATOR . $name;
        file_put_contents($path, $message->getMessage()->toString(), LOCK_EX);
    }

    public function __toString(): string
    {
        return 'file-spool';
    }
}
