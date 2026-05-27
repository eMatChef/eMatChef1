<?php

namespace App\Service\Mail;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Address;

/**
 * Absender- und Reply-To-Metadaten in var/app/mail_outbound.json (nicht DB).
 * Transport: ausschliesslich MAILER_DSN aus der Umgebung (SendGrid-only).
 */
class MailOutboundSettingsStore
{
    private string $filePath;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
        #[Autowire('%env(MAILER_FROM)%')]
        private string $envMailerFrom,
        #[Autowire('%env(MAILER_REPLY_TO)%')]
        private string $envMailerReplyTo,
    ) {
        $this->filePath = $this->projectDir . '/var/app/mail_outbound.json';
    }

    public function getEnvDefaultAddress(): string
    {
        return $this->parseAddressEmail($this->envMailerFrom);
    }

    /**
     * @return array{from_address: string, from_name: ?string, uses_file: bool}
     */
    public function getEffective(): array
    {
        $data = $this->readFile();
        $usesFile = $data !== null;
        $fromAddress = isset($data['from_address']) && is_string($data['from_address']) && $data['from_address'] !== ''
            ? trim($data['from_address'])
            : $this->getEnvDefaultAddress();
        $fromName = null;
        if (isset($data['from_name']) && is_string($data['from_name']) && trim($data['from_name']) !== '') {
            $fromName = trim($data['from_name']);
        }

        return [
            'from_address' => $fromAddress,
            'from_name' => $fromName,
            'uses_file' => $usesFile,
        ];
    }

    /**
     * @return array{
     *   from_address: string,
     *   from_name: ?string,
     *   uses_file: bool,
     *   mailer_reply_to_env: string,
     *   reply_to_address: string,
     *   reply_to_effective: string
     * }
     */
    public function getSettingsForApi(): array
    {
        $eff = $this->getEffective();
        $raw = $this->readFile() ?? [];

        $replyJson = isset($raw['reply_to_address']) ? trim((string) $raw['reply_to_address']) : '';
        $replyEnv = trim($this->envMailerReplyTo);
        $replyEffective = $this->getGlobalReplyToAddress();

        return array_merge($eff, [
            'mailer_reply_to_env' => ($replyEnv !== '' && filter_var($replyEnv, FILTER_VALIDATE_EMAIL)) ? $replyEnv : '',
            'reply_to_address' => ($replyJson !== '' && filter_var($replyJson, FILTER_VALIDATE_EMAIL)) ? $replyJson : '',
            'reply_to_effective' => $replyEffective !== null ? $replyEffective->getAddress() : '',
        ]);
    }

    /**
     * @return array{type: 'dsn', dsn: string, cache_key: string, source: 'env'}
     */
    public function resolveMailTransport(string $fallbackDsn): array
    {
        $fb = trim($fallbackDsn);
        if ($fb !== '' && stripos($fb, 'null://') !== 0) {
            return ['type' => 'dsn', 'dsn' => $fb, 'cache_key' => 'env:' . hash('sha256', $fb), 'source' => 'env'];
        }

        throw new \RuntimeException('MAILER_DSN ist nicht gesetzt oder auf null:// konfiguriert. Versand ist nur ueber SendGrid (MAILER_DSN) erlaubt.');
    }

    /**
     * @return array{mailer_transport_mode: string}
     */
    public function getTransportSummaryForApi(string $fallbackDsn): array
    {
        $fb = trim($fallbackDsn);
        if ($fb !== '' && stripos($fb, 'null://') !== 0) {
            return ['mailer_transport_mode' => 'env'];
        }

        return ['mailer_transport_mode' => 'env_missing'];
    }

    /**
     * Erzeugt den JSON-Payload wie bei Speichern, ohne zu schreiben.
     *
     * @param array<string, mixed> $data gleiche Struktur wie bei {@see save()}
     *
     * @return array<string, mixed>
     */
    public function buildOutboundPayloadForSave(array $data): array
    {
        $existing = $this->readFile() ?? [];

        $email = trim((string) ($data['from_address'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Ungueltige Absender-E-Mail');
        }
        $name = isset($data['from_name']) ? trim((string) $data['from_name']) : '';
        $payload = array_merge($existing, [
            'from_address' => $email,
            'from_name' => $name !== '' ? $name : null,
            'updated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);

        if (array_key_exists('reply_to_address', $data)) {
            $rt = trim((string) $data['reply_to_address']);
            if ($rt === '') {
                unset($payload['reply_to_address']);
            } elseif (!filter_var($rt, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Antwort-Adresse (reply_to_address) ungueltig.');
            } else {
                $payload['reply_to_address'] = $rt;
            }
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(array $data): void
    {
        $payload = $this->buildOutboundPayloadForSave($data);

        $this->ensureDir();
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (file_put_contents($this->filePath, $json, LOCK_EX) === false) {
            throw new \RuntimeException('mail_outbound.json konnte nicht geschrieben werden.');
        }
    }

    public function getFromAddressObject(): Address
    {
        $eff = $this->getEffective();
        $addr = $eff['from_address'];
        $name = $eff['from_name'] ?? null;
        if ($name !== null && $name !== '') {
            return new Address($addr, $name);
        }

        return new Address($addr);
    }

    /**
     * Reply-To für System-Mails: zuerst MAILER_REPLY_TO (Env), sonst reply_to_address in mail_outbound.json.
     * Wenn eine Mail bereits Reply-To setzt (z. B. Kontaktformular), wird das nicht überschrieben.
     */
    public function getGlobalReplyToAddress(): ?Address
    {
        $env = trim($this->envMailerReplyTo);
        if ($env !== '' && filter_var($env, FILTER_VALIDATE_EMAIL)) {
            return new Address($env);
        }

        $data = $this->readFile();
        if ($data !== null && isset($data['reply_to_address']) && is_string($data['reply_to_address'])) {
            $json = trim($data['reply_to_address']);
            if ($json !== '' && filter_var($json, FILTER_VALIDATE_EMAIL)) {
                return new Address($json);
            }
        }

        return null;
    }

    private function readFile(): ?array
    {
        if (!is_readable($this->filePath)) {
            return null;
        }
        $raw = file_get_contents($this->filePath);
        if ($raw === false || trim($raw) === '') {
            return null;
        }
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function ensureDir(): void
    {
        $dir = \dirname($this->filePath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Verzeichnis var/app konnte nicht angelegt werden.');
        }
    }

    private function parseAddressEmail(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return 'noreply@localhost';
        }
        if (preg_match('/<([^>]+)>\s*$/', $raw, $m)) {
            $inner = trim($m[1]);
            if (filter_var($inner, FILTER_VALIDATE_EMAIL)) {
                return $inner;
            }
        }
        if (filter_var($raw, FILTER_VALIDATE_EMAIL)) {
            return $raw;
        }

        return 'noreply@localhost';
    }
}
