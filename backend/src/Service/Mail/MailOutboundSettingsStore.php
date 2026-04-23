<?php

namespace App\Service\Mail;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Address;

/**
 * Absender/SMTP-Metadaten in var/app/mail_outbound.json (nicht DB).
 * Transport-Reihenfolge: zuerst MAILER_DSN aus der Umgebung (wenn nicht null://), sonst vollständiges SMTP aus der JSON-Datei, sonst Datei-Spool.
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
     * API-Payload inkl. SMTP-Metadaten (kein Passwort).
     *
     * @return array{
     *   from_address: string,
     *   from_name: ?string,
     *   uses_file: bool,
     *   use_custom_smtp: bool,
     *   smtp_host: string,
     *   smtp_port: ?int,
     *   smtp_user: string,
     *   smtp_encryption: string,
     *   smtp_password_set: bool,
     *   mailer_reply_to_env: string,
     *   reply_to_address: string,
     *   reply_to_effective: string
     * }
     */
    public function getSettingsForApi(): array
    {
        $eff = $this->getEffective();
        $raw = $this->readFile() ?? [];

        $smtpPasswordSet = isset($raw['smtp_password']) && is_string($raw['smtp_password']) && $raw['smtp_password'] !== '';
        $host = isset($raw['smtp_host']) ? trim((string) $raw['smtp_host']) : '';
        $replyJson = isset($raw['reply_to_address']) ? trim((string) $raw['reply_to_address']) : '';
        $replyEnv = trim($this->envMailerReplyTo);
        $replyEffective = $this->getGlobalReplyToAddress();

        return array_merge($eff, [
            'use_custom_smtp' => $host !== '',
            'smtp_host' => $host,
            'smtp_port' => isset($raw['smtp_port']) ? (int) $raw['smtp_port'] : null,
            'smtp_user' => isset($raw['smtp_user']) ? trim((string) $raw['smtp_user']) : '',
            'smtp_encryption' => $this->normalizeEncryption($raw['smtp_encryption'] ?? 'tls'),
            'smtp_password_set' => $smtpPasswordSet,
            'mailer_reply_to_env' => ($replyEnv !== '' && filter_var($replyEnv, FILTER_VALIDATE_EMAIL)) ? $replyEnv : '',
            'reply_to_address' => ($replyJson !== '' && filter_var($replyJson, FILTER_VALIDATE_EMAIL)) ? $replyJson : '',
            'reply_to_effective' => $replyEffective !== null ? $replyEffective->getAddress() : '',
        ]);
    }

    /**
     * @return array{type: 'dsn', dsn: string, cache_key: string, source: 'env'|'smtp_json'}|array{type: 'file_spool', path: string, cache_key: string}
     */
    public function resolveMailTransport(string $fallbackDsn): array
    {
        $fb = trim($fallbackDsn);
        if ($fb !== '' && stripos($fb, 'null://') !== 0) {
            return ['type' => 'dsn', 'dsn' => $fb, 'cache_key' => 'env:' . hash('sha256', $fb), 'source' => 'env'];
        }

        $data = $this->readFile();
        if ($data !== null && $this->isCompleteSmtp($data)) {
            $dsn = $this->composeSmtpDsn($data);

            return ['type' => 'dsn', 'dsn' => $dsn, 'cache_key' => 'smtp:' . hash('sha256', $dsn), 'source' => 'smtp_json'];
        }

        $dir = $this->getMailSpoolDirectory();

        return ['type' => 'file_spool', 'path' => $dir, 'cache_key' => 'file:' . $dir];
    }

    public function getMailSpoolDirectory(): string
    {
        $dir = $this->projectDir . '/var/app/mail_spool';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    /**
     * @return array{mailer_transport_mode: string, mail_spool_path: ?string, uses_file_spool: bool}
     */
    public function getTransportSummaryForApi(string $fallbackDsn): array
    {
        $r = $this->resolveMailTransport($fallbackDsn);
        if ($r['type'] === 'file_spool') {
            return [
                'mailer_transport_mode' => 'file_spool',
                'mail_spool_path' => $r['path'],
                'uses_file_spool' => true,
            ];
        }

        return [
            'mailer_transport_mode' => $r['source'],
            'mail_spool_path' => null,
            'uses_file_spool' => false,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(array $data): void
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

        $useCustom = !empty($data['use_custom_smtp']);
        if (!$useCustom) {
            unset($payload['smtp_host'], $payload['smtp_port'], $payload['smtp_user'], $payload['smtp_password'], $payload['smtp_encryption']);
        } else {
            $host = trim((string) ($data['smtp_host'] ?? ''));
            $user = trim((string) ($data['smtp_user'] ?? ''));
            $port = (int) ($data['smtp_port'] ?? 0);
            $enc = $this->normalizeEncryption($data['smtp_encryption'] ?? 'tls');

            if ($host === '' || $user === '') {
                throw new \InvalidArgumentException('SMTP: Server und Benutzername sind erforderlich.');
            }

            $pwdIn = $data['smtp_password'] ?? null;
            $password = null;
            if (is_string($pwdIn) && $pwdIn !== '') {
                $password = $pwdIn;
            } elseif (isset($existing['smtp_password']) && is_string($existing['smtp_password']) && $existing['smtp_password'] !== '') {
                $password = $existing['smtp_password'];
            } else {
                throw new \InvalidArgumentException('SMTP: Passwort ist erforderlich (beim ersten Einrichten).');
            }

            if ($port <= 0) {
                $port = $enc === 'ssl' ? 465 : ($enc === 'none' ? 25 : 587);
            }

            $payload['smtp_host'] = $host;
            $payload['smtp_port'] = $port;
            $payload['smtp_user'] = $user;
            $payload['smtp_password'] = $password;
            $payload['smtp_encryption'] = $enc;
        }

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

    /**
     * @param array<string, mixed> $data
     */
    private function isCompleteSmtp(array $data): bool
    {
        $host = isset($data['smtp_host']) ? trim((string) $data['smtp_host']) : '';
        $user = isset($data['smtp_user']) ? trim((string) $data['smtp_user']) : '';
        $pass = isset($data['smtp_password']) && is_string($data['smtp_password']) ? $data['smtp_password'] : '';

        return $host !== '' && $user !== '' && $pass !== '';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function composeSmtpDsn(array $data): string
    {
        $host = trim((string) $data['smtp_host']);
        $user = rawurlencode(trim((string) $data['smtp_user']));
        $pass = rawurlencode((string) $data['smtp_password']);
        $enc = $this->normalizeEncryption($data['smtp_encryption'] ?? 'tls');
        $port = (int) ($data['smtp_port'] ?? 0);

        if ($enc === 'ssl') {
            if ($port <= 0) {
                $port = 465;
            }

            return sprintf('smtps://%s:%s@%s:%d', $user, $pass, $host, $port);
        }

        if ($port <= 0) {
            $port = $enc === 'none' ? 25 : 587;
        }

        $dsn = sprintf('smtp://%s:%s@%s:%d', $user, $pass, $host, $port);
        if ($enc === 'tls') {
            $dsn .= '?encryption=tls';
        }

        return $dsn;
    }

    private function normalizeEncryption(mixed $enc): string
    {
        $e = strtolower(trim((string) $enc));

        return \in_array($e, ['tls', 'ssl', 'none'], true) ? $e : 'tls';
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
