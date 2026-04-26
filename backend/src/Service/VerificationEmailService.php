<?php

namespace App\Service;

use App\Entity\User;
use App\Service\Mail\AppMailer;
use App\Service\Mail\MailOutboundSettingsStore;
use App\Service\Mail\MailSendLogStore;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Email;

class VerificationEmailService
{
    public function __construct(
        private AppMailer $mailer,
        private MailOutboundSettingsStore $mailOutboundSettings,
        private MailSendLogStore $mailSendLog,
        #[Autowire('%env(APP_FRONTEND_URL)%')]
        private string $frontendBaseUrl,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir
    ) {
    }

    public function sendVerificationEmail(User $user): void
    {
        $profile = $user->getProfile();
        if (!$profile) {
            throw new \RuntimeException('Profil fuer Verifikationsmail fehlt.');
        }

        $token = $user->getEmailVerificationToken();
        $expiresAt = $user->getEmailVerificationExpiresAt();
        if (!$token || !$expiresAt) {
            throw new \RuntimeException('Verifikationsdaten fehlen.');
        }

        $verifyUrl = rtrim($this->frontendBaseUrl, '/') . '/verify?token=' . urlencode($token);
        $expiresText = $expiresAt->format('d.m.Y H:i');

        $email = (new Email())
            ->from($this->mailOutboundSettings->getFromAddressObject())
            ->to($profile->getEmail())
            ->subject('Bitte bestaetige deine E-Mail-Adresse')
            ->text(
                "Hallo {$profile->getDisplayName()},\n\n" .
                "bitte bestaetige deine E-Mail-Adresse fuer eMatChef.\n\n" .
                "Bestaetigungslink:\n{$verifyUrl}\n\n" .
                "Der Link ist gueltig bis: {$expiresText}\n\n" .
                "Falls du dich nicht registriert hast, kannst du diese Nachricht ignorieren."
            )
            ->html($this->renderHtmlTemplate('verify_email.html', [
                'brand_header_html' => $this->buildBrandHeaderHtml(),
                'headline' => 'E-Mail bestaetigen',
                'display_name' => $profile->getDisplayName(),
                'intro' => 'bitte bestaetige deine E-Mail-Adresse fuer eMatChef.',
                'extra_block' => '',
                'verify_url' => $verifyUrl,
                'expires_at' => $expiresText,
                'footer_note' => 'Falls du dich nicht registriert hast, kannst du diese Nachricht ignorieren.',
            ], ['brand_header_html', 'extra_block']));

        $this->mailer->send($email);
        $this->mailSendLog->append(
            'auth.verify_email',
            $profile->getEmail(),
            (string) $email->getSubject(),
            $this->mailOutboundSettings->getFromAddressObject()->getAddress()
        );
    }

    public function sendPendingEmailChangeVerification(User $user, string $newEmail, string $token, \DateTime $expiresAt): void
    {
        $profile = $user->getProfile();
        if (!$profile) {
            throw new \RuntimeException('Profil fuer Verifikationsmail fehlt.');
        }

        $verifyUrl = rtrim($this->frontendBaseUrl, '/') . '/verify?token=' . urlencode($token);
        $expiresText = $expiresAt->format('d.m.Y H:i');

        $extraBlock =
            '<p style="margin:0 0 16px 0;font-size:15px;line-height:1.5;color:#374151;">' .
            'Bis zur Bestaetigung bleibt deine bisherige E-Mail-Adresse gueltig.</p>';

        $email = (new Email())
            ->from($this->mailOutboundSettings->getFromAddressObject())
            ->to($newEmail)
            ->subject('Bitte bestaetige deine neue E-Mail-Adresse')
            ->text(
                "Hallo {$profile->getDisplayName()},\n\n" .
                "du hast eine Aenderung deiner E-Mail-Adresse fuer eMatChef angefragt.\n\n" .
                "Neue E-Mail bestaetigen:\n{$verifyUrl}\n\n" .
                "Bis zur Bestaetigung bleibt deine bisherige E-Mail-Adresse gueltig.\n" .
                "Der Link ist gueltig bis: {$expiresText}\n\n" .
                "Falls du diese Aenderung nicht angefragt hast, ignoriere diese Nachricht."
            )
            ->html($this->renderHtmlTemplate('verify_email.html', [
                'brand_header_html' => $this->buildBrandHeaderHtml(),
                'headline' => 'Neue E-Mail bestaetigen',
                'display_name' => $profile->getDisplayName(),
                'intro' => 'du hast eine Aenderung deiner E-Mail-Adresse fuer eMatChef angefragt.',
                'extra_block' => $extraBlock,
                'verify_url' => $verifyUrl,
                'expires_at' => $expiresText,
                'footer_note' => 'Falls du diese Aenderung nicht angefragt hast, ignoriere diese Nachricht.',
            ], ['brand_header_html', 'extra_block']));

        $this->mailer->send($email);
        $this->mailSendLog->append(
            'auth.pending_email_change',
            $newEmail,
            (string) $email->getSubject(),
            $this->mailOutboundSettings->getFromAddressObject()->getAddress()
        );
    }

    public function sendDepartmentInviteEmail(
        string $recipientEmail,
        string $recipientName,
        string $inviterName,
        string $departmentName,
        string $inviteUrl,
        string $roleLabel
    ): void {
        $safeRecipient = trim($recipientName) !== '' ? $recipientName : $recipientEmail;
        $subject = 'Einladung zu eMatChef Department: ' . $departmentName;

        $email = (new Email())
            ->from($this->mailOutboundSettings->getFromAddressObject())
            ->to($recipientEmail)
            ->subject($subject)
            ->text(
                "Hallo {$safeRecipient},\n\n" .
                "{$inviterName} hat dich zu dem Department \"{$departmentName}\" eingeladen.\n" .
                "Vorgesehene Rolle: {$roleLabel}\n\n" .
                "Einladungslink:\n{$inviteUrl}\n\n" .
                "Falls du noch kein Konto hast, kannst du dich ueber den Link registrieren " .
                "und wirst danach direkt dem Department zugeordnet.\n\n" .
                "Wenn du diese Einladung nicht erwartest, kannst du diese E-Mail ignorieren."
            )
            ->html($this->renderHtmlTemplate('department_invite.html', [
                'brand_header_html' => $this->buildBrandHeaderHtml(),
                'recipient_name' => $safeRecipient,
                'inviter_name' => $inviterName,
                'department_name' => $departmentName,
                'role_label' => $roleLabel,
                'invite_url' => $inviteUrl,
            ], ['brand_header_html']));

        $this->mailer->send($email);
        $this->mailSendLog->append(
            'department.invite',
            $recipientEmail,
            $subject,
            $this->mailOutboundSettings->getFromAddressObject()->getAddress()
        );
    }

    public function sendPasswordResetCode(User $user, string $code, \DateTime $expiresAt): void
    {
        $profile = $user->getProfile();
        if (!$profile) {
            throw new \RuntimeException('Profil fuer Passwort-Reset fehlt.');
        }

        $expiresText = $expiresAt->format('d.m.Y H:i:s');

        $email = (new Email())
            ->from($this->mailOutboundSettings->getFromAddressObject())
            ->to($profile->getEmail())
            ->subject('Passwort zuruecksetzen - eMatChef')
            ->text(
                "Hallo {$profile->getDisplayName()},\n\n" .
                "du hast ein neues Passwort fuer eMatChef angefordert.\n\n" .
                "Dein Sicherheitscode lautet: {$code}\n\n" .
                "Der Code ist gueltig bis: {$expiresText}\n" .
                "Bitte gib den Code zusammen mit deinem neuen Passwort im Login-Bereich ein.\n\n" .
                "Falls du diese Anfrage nicht gestellt hast, ignoriere diese E-Mail."
            )
            ->html($this->renderHtmlTemplate('password_reset_code.html', [
                'brand_header_html' => $this->buildBrandHeaderHtml(),
                'display_name' => $profile->getDisplayName(),
                'reset_code' => strtoupper($code),
                'expires_at' => $expiresText,
            ], ['brand_header_html']));

        $this->mailer->send($email);
        $this->mailSendLog->append(
            'auth.password_reset_code',
            $profile->getEmail(),
            (string) $email->getSubject(),
            $this->mailOutboundSettings->getFromAddressObject()->getAddress()
        );
    }

    /**
     * @param list<string> $unescapedKeys Platzhalter, die bereits sicheres HTML enthalten (z. B. Logo-Block)
     */
    private function renderHtmlTemplate(string $templateFile, array $variables, array $unescapedKeys = []): string
    {
        $fullPath = $this->projectDir . '/templates/emails/' . $templateFile;
        if (!is_file($fullPath) || !is_readable($fullPath)) {
            throw new \RuntimeException(sprintf('Mail-Template nicht gefunden: %s', $templateFile));
        }

        $template = file_get_contents($fullPath);
        if ($template === false) {
            throw new \RuntimeException(sprintf('Mail-Template konnte nicht geladen werden: %s', $templateFile));
        }

        $raw = array_flip($unescapedKeys);
        $replace = [];
        foreach ($variables as $key => $value) {
            $replace['{{' . $key . '}}'] = isset($raw[$key])
                ? (string) $value
                : htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return strtr($template, $replace);
    }

    /**
     * Optionales Marken-Logo: absolute HTTPS-URL in Umgebungsvariable MAILER_BRAND_LOGO_URL
     * (z. B. https://ematchef.ch/favicon.svg auf Hostpoint).
     */
    private function buildBrandHeaderHtml(): string
    {
        $raw = getenv('MAILER_BRAND_LOGO_URL');
        $url = is_string($raw) ? trim($raw) : '';
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }
        if (!str_starts_with(strtolower($url), 'https://')) {
            return '';
        }
        $safe = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<img src="' . $safe . '" alt="eMatChef" width="140" style="max-width:180px;height:auto;display:block;margin:0 auto;border:0;" />';
    }

    public function getMailTemplateCatalog(): array
    {
        return [
            [
                'key' => 'auth.verify_email',
                'title' => 'Registrierung - E-Mail bestaetigen',
                'subject' => 'Bitte bestaetige deine E-Mail-Adresse',
                'description' => 'Wird direkt nach der Registrierung versendet. HTML-Layout inkl. Button (templates/emails/verify_email.html). Optional: MAILER_BRAND_LOGO_URL (HTTPS-Bild).',
                'body_preview' =>
                    "Hallo {{display_name}},\n\n" .
                    "bitte bestaetige deine E-Mail-Adresse fuer eMatChef.\n\n" .
                    "Bestaetigungslink:\n{{verify_url}}\n\n" .
                    "Der Link ist gueltig bis: {{expires_at}}",
            ],
            [
                'key' => 'auth.pending_email_change',
                'title' => 'Profil - Neue E-Mail bestaetigen',
                'subject' => 'Bitte bestaetige deine neue E-Mail-Adresse',
                'description' => 'Wird bei der Aenderung der E-Mail-Adresse versendet. Gleiches HTML-Layout wie Registrierung (verify_email.html).',
                'body_preview' =>
                    "Hallo {{display_name}},\n\n" .
                    "du hast eine Aenderung deiner E-Mail-Adresse fuer eMatChef angefragt.\n\n" .
                    "Neue E-Mail bestaetigen:\n{{verify_url}}\n\n" .
                    "Der Link ist gueltig bis: {{expires_at}}",
            ],
            [
                'key' => 'auth.password_reset_code',
                'title' => 'Login - Passwort zuruecksetzen',
                'subject' => 'Passwort zuruecksetzen - eMatChef',
                'description' => 'Wird bei "Passwort vergessen" versendet.',
                'body_preview' =>
                    "Hallo {{display_name}},\n\n" .
                    "du hast ein neues Passwort fuer eMatChef angefordert.\n\n" .
                    "Dein Sicherheitscode lautet: {{reset_code}}\n\n" .
                    "Der Code ist gueltig bis: {{expires_at}}",
            ],
            [
                'key' => 'department.invite',
                'title' => 'Department - Einladung',
                'subject' => 'Einladung zu eMatChef Department: {{department_name}}',
                'description' => 'Wird im Onboarding/Department bei "Einladung senden" versendet.',
                'body_preview' =>
                    "Hallo {{recipient_name}},\n\n" .
                    "{{inviter_name}} hat dich zu dem Department \"{{department_name}}\" eingeladen.\n" .
                    "Vorgesehene Rolle: {{role_label}}\n\n" .
                    "Einladungslink:\n{{invite_url}}",
            ],
            [
                'key' => 'public.found_item_contact',
                'title' => 'Öffentlich - Fund-Hinweis',
                'subject' => '[eMatChef] Hinweis: Artikel gefunden – {{material_name}}',
                'description' => 'Wird bei Kontakt über den öffentlichen QR-/Material-Link versendet (an die Abteilungs-Kontaktadresse).',
                'body_preview' => "Artikel, Abteilung, Public-Link, Nachricht des Absenders (kein vollständiger Text in diesem Katalog).",
            ],
        ];
    }
}
