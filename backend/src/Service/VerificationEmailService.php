<?php

namespace App\Service;

use App\Entity\User;
use App\Service\Mail\AppMailer;
use App\Service\Mail\MailOutboundSettingsStore;
use App\Service\Mail\MailSendLogStore;
use App\Service\Mail\MailTemplateContentStore;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Email;

class VerificationEmailService
{
    public function __construct(
        private AppMailer $mailer,
        private MailOutboundSettingsStore $mailOutboundSettings,
        private MailSendLogStore $mailSendLog,
        private MailTemplateContentStore $mailTemplateContent,
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
            throw $this->vex('no_profile', 'de');
        }

        $token = $user->getEmailVerificationToken();
        $expiresAt = $user->getEmailVerificationExpiresAt();
        if (!$token || !$expiresAt) {
            throw $this->vex('verify_data', 'de');
        }

        $verifyUrl = rtrim($this->frontendBaseUrl, '/') . '/verify?token=' . urlencode($token);
        $expiresText = $expiresAt->format('d.m.Y H:i');
        $locale = $this->mailTemplateContent->resolveMailLocale($profile->getLanguage());

        $tpl = $this->mailTemplateContent->getTemplate('auth.verify_email', $locale);
        if ($tpl === null) {
            throw $this->vex('tpl_auth_verify', $locale);
        }

        $vars = [
            'display_name' => $profile->getDisplayName(),
            'verify_url' => $verifyUrl,
            'expires_at' => $expiresText,
        ];
        $subject = $this->mailTemplateContent->interpolate((string) ($tpl['subject'] ?? ''), $vars);
        $textBody = $this->mailTemplateContent->interpolate((string) ($tpl['text_body'] ?? ''), $vars);
        $htmlCfg = is_array($tpl['html'] ?? null) ? $tpl['html'] : [];
        $extraBlock = (string) ($htmlCfg['extra_block'] ?? '');

        $email = (new Email())
            ->from($this->mailOutboundSettings->getFromAddressObject())
            ->to($profile->getEmail())
            ->subject($subject)
            ->text($textBody)
            ->html($this->renderHtmlTemplate('verify_email.html', [
                'brand_header_html' => $this->buildBrandHeaderHtml($locale),
                'headline' => (string) ($htmlCfg['headline'] ?? ''),
                'greeting_word' => (string) ($htmlCfg['greeting_word'] ?? ''),
                'display_name' => $profile->getDisplayName(),
                'intro' => (string) ($htmlCfg['intro'] ?? ''),
                'extra_block' => $extraBlock,
                'cta_label' => (string) ($htmlCfg['cta_label'] ?? ''),
                'link_hint' => (string) ($htmlCfg['link_hint'] ?? ''),
                'expires_intro' => (string) ($htmlCfg['expires_intro'] ?? ''),
                'verify_url' => $verifyUrl,
                'expires_at' => $expiresText,
                'footer_note' => (string) ($htmlCfg['footer_note'] ?? ''),
            ], ['brand_header_html', 'extra_block'], $locale));

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
            throw $this->vex('no_profile', 'de');
        }

        $verifyUrl = rtrim($this->frontendBaseUrl, '/') . '/verify?token=' . urlencode($token);
        $expiresText = $expiresAt->format('d.m.Y H:i');
        $locale = $this->mailTemplateContent->resolveMailLocale($profile->getLanguage());

        $tpl = $this->mailTemplateContent->getTemplate('auth.pending_email_change', $locale);
        if ($tpl === null) {
            throw $this->vex('tpl_pending', $locale);
        }

        $vars = [
            'display_name' => $profile->getDisplayName(),
            'verify_url' => $verifyUrl,
            'expires_at' => $expiresText,
        ];
        $subject = $this->mailTemplateContent->interpolate((string) ($tpl['subject'] ?? ''), $vars);
        $textBody = $this->mailTemplateContent->interpolate((string) ($tpl['text_body'] ?? ''), $vars);
        $htmlCfg = is_array($tpl['html'] ?? null) ? $tpl['html'] : [];
        $extraBlock = (string) ($htmlCfg['extra_block'] ?? '');

        $email = (new Email())
            ->from($this->mailOutboundSettings->getFromAddressObject())
            ->to($newEmail)
            ->subject($subject)
            ->text($textBody)
            ->html($this->renderHtmlTemplate('verify_email.html', [
                'brand_header_html' => $this->buildBrandHeaderHtml($locale),
                'headline' => (string) ($htmlCfg['headline'] ?? ''),
                'greeting_word' => (string) ($htmlCfg['greeting_word'] ?? ''),
                'display_name' => $profile->getDisplayName(),
                'intro' => (string) ($htmlCfg['intro'] ?? ''),
                'extra_block' => $extraBlock,
                'cta_label' => (string) ($htmlCfg['cta_label'] ?? ''),
                'link_hint' => (string) ($htmlCfg['link_hint'] ?? ''),
                'expires_intro' => (string) ($htmlCfg['expires_intro'] ?? ''),
                'verify_url' => $verifyUrl,
                'expires_at' => $expiresText,
                'footer_note' => (string) ($htmlCfg['footer_note'] ?? ''),
            ], ['brand_header_html', 'extra_block'], $locale));

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
        string $roleLabel,
        ?string $recipientLocale = null
    ): void {
        $locale = $this->mailTemplateContent->normalizeLocaleParam(trim((string) ($recipientLocale ?? '')));

        $tpl = $this->mailTemplateContent->getTemplate('department.invite', $locale);
        if ($tpl === null) {
            throw $this->vex('tpl_dept', $locale);
        }

        $safeRecipient = trim($recipientName) !== '' ? $recipientName : $recipientEmail;
        $vars = [
            'recipient_name' => $safeRecipient,
            'inviter_name' => $inviterName,
            'department_name' => $departmentName,
            'role_label' => $roleLabel,
            'invite_url' => $inviteUrl,
        ];
        $subject = $this->mailTemplateContent->interpolate((string) ($tpl['subject'] ?? ''), $vars);
        $textBody = $this->mailTemplateContent->interpolate((string) ($tpl['text_body'] ?? ''), $vars);
        $htmlCfg = is_array($tpl['html'] ?? null) ? $tpl['html'] : [];

        $inviteLead = strtr((string) ($htmlCfg['invite_lead_template'] ?? ''), [
            '{{inviter_name}}' => htmlspecialchars($inviterName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '{{department_name}}' => htmlspecialchars($departmentName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        ]);
        $roleLine = strtr((string) ($htmlCfg['role_line_template'] ?? ''), [
            '{{role_label}}' => htmlspecialchars($roleLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        ]);

        $email = (new Email())
            ->from($this->mailOutboundSettings->getFromAddressObject())
            ->to($recipientEmail)
            ->subject($subject)
            ->text($textBody)
            ->html($this->renderHtmlTemplate('department_invite.html', [
                'brand_header_html' => $this->buildBrandHeaderHtml($locale),
                'recipient_name' => $safeRecipient,
                'greeting_word' => (string) ($htmlCfg['greeting_word'] ?? ''),
                'banner_title' => (string) ($htmlCfg['banner_title'] ?? ''),
                'invite_lead_html' => $inviteLead,
                'role_line_html' => $roleLine,
                'cta_label' => (string) ($htmlCfg['cta_label'] ?? ''),
                'link_hint' => (string) ($htmlCfg['link_hint'] ?? ''),
                'invite_url' => $inviteUrl,
                'footer_note' => (string) ($htmlCfg['footer_note'] ?? ''),
            ], ['brand_header_html', 'invite_lead_html', 'role_line_html'], $locale));

        $this->mailer->send($email);
        $this->mailSendLog->append(
            'department.invite',
            $recipientEmail,
            $subject,
            $this->mailOutboundSettings->getFromAddressObject()->getAddress()
        );
    }

    public function sendDepartmentMemberAddedEmail(
        string $recipientEmail,
        string $recipientName,
        string $adderName,
        string $departmentName,
        string $roleLabel,
        ?string $recipientLocale = null
    ): void {
        $locale = $this->mailTemplateContent->normalizeLocaleParam(trim((string) ($recipientLocale ?? '')));

        $tpl = $this->mailTemplateContent->getTemplate('department.member_added', $locale);
        if ($tpl === null) {
            throw $this->vex('tpl_dept_member', $locale);
        }

        $appUrl = rtrim($this->frontendBaseUrl, '/') . '/login';
        $safeRecipient = trim($recipientName) !== '' ? $recipientName : $recipientEmail;
        $vars = [
            'recipient_name' => $safeRecipient,
            'adder_name' => $adderName,
            'department_name' => $departmentName,
            'role_label' => $roleLabel,
            'app_url' => $appUrl,
        ];
        $subject = $this->mailTemplateContent->interpolate((string) ($tpl['subject'] ?? ''), $vars);
        $textBody = $this->mailTemplateContent->interpolate((string) ($tpl['text_body'] ?? ''), $vars);
        $htmlCfg = is_array($tpl['html'] ?? null) ? $tpl['html'] : [];

        $addedLead = strtr((string) ($htmlCfg['added_lead_template'] ?? ''), [
            '{{adder_name}}' => htmlspecialchars($adderName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '{{department_name}}' => htmlspecialchars($departmentName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        ]);
        $roleLine = strtr((string) ($htmlCfg['role_line_template'] ?? ''), [
            '{{role_label}}' => htmlspecialchars($roleLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        ]);

        $email = (new Email())
            ->from($this->mailOutboundSettings->getFromAddressObject())
            ->to($recipientEmail)
            ->subject($subject)
            ->text($textBody)
            ->html($this->renderHtmlTemplate('department_member_added.html', [
                'brand_header_html' => $this->buildBrandHeaderHtml($locale),
                'recipient_name' => $safeRecipient,
                'greeting_word' => (string) ($htmlCfg['greeting_word'] ?? ''),
                'banner_title' => (string) ($htmlCfg['banner_title'] ?? ''),
                'added_lead_html' => $addedLead,
                'role_line_html' => $roleLine,
                'cta_label' => (string) ($htmlCfg['cta_label'] ?? ''),
                'link_hint' => (string) ($htmlCfg['link_hint'] ?? ''),
                'app_url' => $appUrl,
                'footer_note' => (string) ($htmlCfg['footer_note'] ?? ''),
            ], ['brand_header_html', 'added_lead_html', 'role_line_html'], $locale));

        $this->mailer->send($email);
        $this->mailSendLog->append(
            'department.member_added',
            $recipientEmail,
            $subject,
            $this->mailOutboundSettings->getFromAddressObject()->getAddress()
        );
    }

    public function sendPasswordResetCode(User $user, string $code, \DateTime $expiresAt): void
    {
        $profile = $user->getProfile();
        if (!$profile) {
            throw $this->vex('no_profile_pwd', 'de');
        }

        $expiresText = $expiresAt->format('d.m.Y H:i:s');
        $locale = $this->mailTemplateContent->resolveMailLocale($profile->getLanguage());

        $tpl = $this->mailTemplateContent->getTemplate('auth.password_reset_code', $locale);
        if ($tpl === null) {
            throw $this->vex('tpl_pwd', $locale);
        }

        $vars = [
            'display_name' => $profile->getDisplayName(),
            'reset_code' => strtoupper($code),
            'expires_at' => $expiresText,
        ];
        $subject = $this->mailTemplateContent->interpolate((string) ($tpl['subject'] ?? ''), $vars);
        $textBody = $this->mailTemplateContent->interpolate((string) ($tpl['text_body'] ?? ''), $vars);
        $htmlCfg = is_array($tpl['html'] ?? null) ? $tpl['html'] : [];

        $email = (new Email())
            ->from($this->mailOutboundSettings->getFromAddressObject())
            ->to($profile->getEmail())
            ->subject($subject)
            ->text($textBody)
            ->html($this->renderHtmlTemplate('password_reset_code.html', [
                'brand_header_html' => $this->buildBrandHeaderHtml($locale),
                'greeting_word' => (string) ($htmlCfg['greeting_word'] ?? ''),
                'display_name' => $profile->getDisplayName(),
                'headline' => (string) ($htmlCfg['headline'] ?? ''),
                'intro' => (string) ($htmlCfg['intro'] ?? ''),
                'code_label' => (string) ($htmlCfg['code_label'] ?? ''),
                'reset_code' => strtoupper($code),
                'expires_intro' => (string) ($htmlCfg['expires_intro'] ?? ''),
                'expires_at' => $expiresText,
                'instruction' => (string) ($htmlCfg['instruction'] ?? ''),
                'footer_note' => (string) ($htmlCfg['footer_note'] ?? ''),
            ], ['brand_header_html'], $locale));

        $this->mailer->send($email);
        $this->mailSendLog->append(
            'auth.password_reset_code',
            $profile->getEmail(),
            (string) $email->getSubject(),
            $this->mailOutboundSettings->getFromAddressObject()->getAddress()
        );
    }

    private function vex(string $key, string $locale): \RuntimeException
    {
        return new \RuntimeException($this->mailTemplateContent->getApiString('vex.' . $key, $locale));
    }

    /**
     * @param list<string> $unescapedKeys Platzhalter, die bereits sicheres HTML enthalten (z. B. Logo-Block)
     */
    private function renderHtmlTemplate(string $templateFile, array $variables, array $unescapedKeys = [], string $locale = 'de'): string
    {
        $fullPath = $this->projectDir . '/templates/emails/' . $templateFile;
        if (!is_file($fullPath) || !is_readable($fullPath)) {
            $msg = $this->mailTemplateContent->interpolate(
                $this->mailTemplateContent->getApiString('vex.html_not_found', $locale),
                ['file' => $templateFile]
            );
            throw new \RuntimeException($msg);
        }

        $template = file_get_contents($fullPath);
        if ($template === false) {
            $msg = $this->mailTemplateContent->interpolate(
                $this->mailTemplateContent->getApiString('vex.html_unreadable', $locale),
                ['file' => $templateFile]
            );
            throw new \RuntimeException($msg);
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
    private function buildBrandHeaderHtml(string $locale): string
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
        $alt = htmlspecialchars($this->mailTemplateContent->getSharedString('brand_logo_alt', $locale), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<img src="' . $safe . '" alt="' . $alt . '" width="140" style="max-width:180px;height:auto;display:block;margin:0 auto;border:0;" />';
    }
}
