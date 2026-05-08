<?php

namespace App\Service\Mail;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Append-only Log in var/app/mail_send_log.json (Array, max. Eintraege begrenzt).
 */
class MailSendLogStore
{
    private const MAX_ENTRIES = 500;

    private string $filePath;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir
    ) {
        $this->filePath = $this->projectDir . '/var/app/mail_send_log.json';
    }

    /**
     * @param string|null $from Absender-Adresse (kein Passwort); optional für Nachvollziehbarkeit.
     */
    public function append(string $kind, string $to, string $subject, ?string $from = null): void
    {
        try {
            $to = trim($to);
            if ($to === '') {
                $to = '(unbekannt)';
            }
            $subject = mb_substr(trim($subject), 0, 200);
            $fromLog = trim((string) $from);
            if ($fromLog !== '') {
                $fromLog = mb_substr($fromLog, 0, 200);
            }

            $entries = $this->readAll();
            $entries[] = [
                'at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'kind' => $kind,
                'to' => $to,
                'subject' => $subject,
                'from' => $fromLog,
            ];
            if (\count($entries) > self::MAX_ENTRIES) {
                $entries = \array_slice($entries, -self::MAX_ENTRIES);
            }
            $this->writeAll($entries);
        } catch (\Throwable) {
            // Mailversand nicht blockieren, wenn Log-Datei nicht beschreibbar ist
        }
    }

    /**
     * @return list<array{at: string, kind: string, to: string, subject: string, from: string}>
     */
    public function getRecent(int $limit = 100): array
    {
        $all = $this->readAll();
        $limit = max(1, min(500, $limit));
        if (\count($all) <= $limit) {
            return array_reverse($all);
        }

        return array_reverse(\array_slice($all, -$limit));
    }

    /**
     * @return list<array{at: string, kind: string, to: string, subject: string, from: string}>
     */
    private function readAll(): array
    {
        if (!is_readable($this->filePath)) {
            return [];
        }
        $raw = file_get_contents($this->filePath);
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
        if (!\is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $out[] = [
                'at' => (string) ($row['at'] ?? ''),
                'kind' => (string) ($row['kind'] ?? ''),
                'to' => (string) ($row['to'] ?? ''),
                'subject' => (string) ($row['subject'] ?? ''),
                'from' => (string) ($row['from'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @param list<array{at: string, kind: string, to: string, subject: string, from: string}> $entries
     */
    private function writeAll(array $entries): void
    {
        $dir = \dirname($this->filePath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Verzeichnis var/app konnte nicht angelegt werden.');
        }
        $json = json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (file_put_contents($this->filePath, $json, LOCK_EX) === false) {
            throw new \RuntimeException('mail_send_log.json konnte nicht geschrieben werden.');
        }
    }
}
