<?php

namespace App\Service;

use App\Entity\Department;
use App\Entity\User;
use App\Service\Mail\AppMailer;
use App\Service\Mail\MailOutboundSettingsStore;
use App\Service\Mail\MailLogKind;
use App\Service\Mail\MailTemplateContentStore;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Email;

class VerificationEmailService
{
    public function __construct(
        private AppMailer $mailer,
        private MailOutboundSettingsStore $mailOutboundSettings,
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
        $locale = $this->mailTemplateContent->resolveMailLocale($profile->getLanguage());
        $expiresText = $this->formatMailExpiresAt($expiresAt, $locale);

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
            ->html($this->renderFramedMail('verify_email.html', [
                'preheader' => $this->mailPreheader($htmlCfg, $vars),
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
            ], ['extra_block'], $locale));

        $this->mailer->send(MailLogKind::stamp($email, 'auth.verify_email'));
    }

    public function sendPendingEmailChangeVerification(User $user, string $newEmail, string $token, \DateTime $expiresAt): void
    {
        $profile = $user->getProfile();
        if (!$profile) {
            throw $this->vex('no_profile', 'de');
        }

        $verifyUrl = rtrim($this->frontendBaseUrl, '/') . '/verify?token=' . urlencode($token);
        $locale = $this->mailTemplateContent->resolveMailLocale($profile->getLanguage());
        $expiresText = $this->formatMailExpiresAt($expiresAt, $locale);

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
            ->html($this->renderFramedMail('verify_email.html', [
                'preheader' => $this->mailPreheader($htmlCfg, $vars),
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
            ], ['extra_block'], $locale));

        $this->mailer->send(MailLogKind::stamp($email, 'auth.pending_email_change'));
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
            ->html($this->renderFramedMail('department_invite.html', [
                'preheader' => $this->mailPreheader($htmlCfg, $vars),
                'recipient_name' => $safeRecipient,
                'department_name' => $departmentName,
                'greeting_word' => (string) ($htmlCfg['greeting_word'] ?? ''),
                'banner_title' => (string) ($htmlCfg['banner_title'] ?? ''),
                'invite_lead_html' => $inviteLead,
                'role_line_html' => $roleLine,
                'cta_label' => (string) ($htmlCfg['cta_label'] ?? ''),
                'password_hint' => (string) ($htmlCfg['password_hint'] ?? ''),
                'link_hint' => (string) ($htmlCfg['link_hint'] ?? ''),
                'invite_url' => $inviteUrl,
                'footer_note' => (string) ($htmlCfg['footer_note'] ?? ''),
            ], ['invite_lead_html', 'role_line_html'], $locale));

        $this->mailer->send(MailLogKind::stamp($email, 'department.invite'));
    }

    public function sendDepartmentMemberAddedEmail(
        string $recipientEmail,
        string $recipientName,
        string $adderName,
        string $departmentName,
        string $roleLabel,
        string $departmentId,
        bool $isGrossanlass = false,
        ?string $recipientLocale = null
    ): void {
        $locale = $this->mailTemplateContent->normalizeLocaleParam(trim((string) ($recipientLocale ?? '')));

        $tpl = $this->mailTemplateContent->getTemplate('department.member_added', $locale);
        if ($tpl === null) {
            throw $this->vex('tpl_dept_member', $locale);
        }

        $appUrl = $this->buildDepartmentMemberAddedAppUrl($departmentId, $isGrossanlass);
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
            ->html($this->renderFramedMail('department_member_added.html', [
                'preheader' => $this->mailPreheader($htmlCfg, $vars),
                'recipient_name' => $safeRecipient,
                'department_name' => $departmentName,
                'greeting_word' => (string) ($htmlCfg['greeting_word'] ?? ''),
                'banner_title' => (string) ($htmlCfg['banner_title'] ?? ''),
                'added_lead_html' => $addedLead,
                'role_line_html' => $roleLine,
                'cta_label' => (string) ($htmlCfg['cta_label'] ?? ''),
                'link_hint' => (string) ($htmlCfg['link_hint'] ?? ''),
                'app_url' => $appUrl,
                'footer_note' => (string) ($htmlCfg['footer_note'] ?? ''),
            ], ['added_lead_html', 'role_line_html'], $locale));

        $this->mailer->send(MailLogKind::stamp($email, 'department.member_added'));
    }

    public function sendJoinRequestManagerNotification(
        string $recipientEmail,
        string $recipientName,
        string $requesterName,
        string $requesterEmail,
        string $departmentName,
        string $organisationName,
        ?string $message,
        string $reviewUrl,
        ?string $recipientLocale = null,
    ): void {
        $locale = $this->mailTemplateContent->normalizeLocaleParam(trim((string) ($recipientLocale ?? '')));
        $tpl = $this->mailTemplateContent->getTemplate('join_request.manager_notify', $locale);
        if ($tpl === null) {
            throw $this->vex('tpl_join_mgr', $locale);
        }

        $messageBlock = trim((string) ($message ?? ''));
        $messageLine = $messageBlock !== ''
            ? "\nNachricht: {$messageBlock}\n"
            : '';

        $safeRecipient = trim($recipientName) !== '' ? $recipientName : $recipientEmail;
        $vars = [
            'recipient_name' => $safeRecipient,
            'requester_name' => $requesterName,
            'requester_email' => $requesterEmail,
            'department_name' => $departmentName,
            'organisation_name' => $organisationName,
            'message_line' => $messageLine,
            'review_url' => $reviewUrl,
        ];
        $subject = $this->mailTemplateContent->interpolate((string) ($tpl['subject'] ?? ''), $vars);
        $textBody = $this->mailTemplateContent->interpolate((string) ($tpl['text_body'] ?? ''), $vars);
        $htmlCfg = is_array($tpl['html'] ?? null) ? $tpl['html'] : [];

        $leadHtml = strtr((string) ($htmlCfg['lead_template'] ?? ''), [
            '{{requester_name}}' => htmlspecialchars($requesterName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '{{requester_email}}' => htmlspecialchars($requesterEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '{{department_name}}' => htmlspecialchars($departmentName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '{{organisation_name}}' => htmlspecialchars($organisationName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        ]);
        $messageHtml = $messageBlock !== ''
            ? '<p style="margin:0 0 12px 0;font-size:14px;color:#374151;"><strong>Nachricht:</strong> '
                . htmlspecialchars($messageBlock, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
            : '';

        $email = (new Email())
            ->from($this->mailOutboundSettings->getFromAddressObject())
            ->to($recipientEmail)
            ->subject($subject)
            ->text($textBody)
            ->html($this->renderFramedMail('join_request_manager.html', [
                'preheader' => $this->mailPreheader($htmlCfg, $vars),
                'recipient_name' => $safeRecipient,
                'greeting_word' => (string) ($htmlCfg['greeting_word'] ?? ''),
                'banner_title' => (string) ($htmlCfg['banner_title'] ?? ''),
                'lead_html' => $leadHtml,
                'message_html' => $messageHtml,
                'cta_label' => (string) ($htmlCfg['cta_label'] ?? ''),
                'link_hint' => (string) ($htmlCfg['link_hint'] ?? ''),
                'review_url' => $reviewUrl,
                'footer_note' => (string) ($htmlCfg['footer_note'] ?? ''),
            ], ['lead_html', 'message_html'], $locale));

        $this->mailer->send(MailLogKind::stamp($email, 'join_request.manager_notify'));
    }

    public function sendAdminJoinRequestManagerNotification(
        string $recipientEmail,
        string $recipientName,
        string $requesterName,
        string $requesterEmail,
        string $requestedDepartmentName,
        string $organisationName,
        ?string $parentDepartmentName,
        ?string $message,
        string $reviewUrl,
        ?string $recipientLocale = null,
    ): void {
        $locale = $this->mailTemplateContent->normalizeLocaleParam(trim((string) ($recipientLocale ?? '')));
        $tpl = $this->mailTemplateContent->getTemplate('admin_join_request.manager_notify', $locale);
        if ($tpl === null) {
            throw $this->vex('tpl_admin_join_mgr', $locale);
        }

        $parentLine = trim((string) ($parentDepartmentName ?? '')) !== ''
            ? "\nUebergeordnete Abteilung: {$parentDepartmentName}\n"
            : '';
        $messageBlock = trim((string) ($message ?? ''));
        $messageLine = $messageBlock !== ''
            ? "\nNachricht: {$messageBlock}\n"
            : '';

        $safeRecipient = trim($recipientName) !== '' ? $recipientName : $recipientEmail;
        $vars = [
            'recipient_name' => $safeRecipient,
            'requester_name' => $requesterName,
            'requester_email' => $requesterEmail,
            'requested_department_name' => $requestedDepartmentName,
            'organisation_name' => $organisationName,
            'parent_line' => $parentLine,
            'message_line' => $messageLine,
            'review_url' => $reviewUrl,
        ];
        $subject = $this->mailTemplateContent->interpolate((string) ($tpl['subject'] ?? ''), $vars);
        $textBody = $this->mailTemplateContent->interpolate((string) ($tpl['text_body'] ?? ''), $vars);
        $htmlCfg = is_array($tpl['html'] ?? null) ? $tpl['html'] : [];

        $leadHtml = strtr((string) ($htmlCfg['lead_template'] ?? ''), [
            '{{requester_name}}' => htmlspecialchars($requesterName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '{{requester_email}}' => htmlspecialchars($requesterEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '{{requested_department_name}}' => htmlspecialchars($requestedDepartmentName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '{{organisation_name}}' => htmlspecialchars($organisationName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        ]);
        $parentHtml = trim((string) ($parentDepartmentName ?? '')) !== ''
            ? '<p style="margin:0 0 12px 0;font-size:14px;color:#374151;"><strong>Uebergeordnete Abteilung:</strong> '
                . htmlspecialchars($parentDepartmentName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
            : '';
        $messageHtml = $messageBlock !== ''
            ? '<p style="margin:0 0 12px 0;font-size:14px;color:#374151;"><strong>Nachricht:</strong> '
                . htmlspecialchars($messageBlock, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
            : '';

        $email = (new Email())
            ->from($this->mailOutboundSettings->getFromAddressObject())
            ->to($recipientEmail)
            ->subject($subject)
            ->text($textBody)
            ->html($this->renderFramedMail('join_request_manager.html', [
                'preheader' => $this->mailPreheader($htmlCfg, $vars),
                'recipient_name' => $safeRecipient,
                'greeting_word' => (string) ($htmlCfg['greeting_word'] ?? ''),
                'banner_title' => (string) ($htmlCfg['banner_title'] ?? ''),
                'lead_html' => $leadHtml,
                'message_html' => $parentHtml . $messageHtml,
                'cta_label' => (string) ($htmlCfg['cta_label'] ?? ''),
                'link_hint' => (string) ($htmlCfg['link_hint'] ?? ''),
                'review_url' => $reviewUrl,
                'footer_note' => (string) ($htmlCfg['footer_note'] ?? ''),
            ], ['lead_html', 'message_html'], $locale));

        $this->mailer->send(MailLogKind::stamp($email, 'admin_join_request.manager_notify'));
    }

    public function sendPasswordResetCode(User $user, string $code, \DateTime $expiresAt): void
    {
        $profile = $user->getProfile();
        if (!$profile) {
            throw $this->vex('no_profile_pwd', 'de');
        }

        $locale = $this->mailTemplateContent->resolveMailLocale($profile->getLanguage());
        $expiresText = $this->formatMailExpiresAt($expiresAt, $locale);
        $resetUrl = rtrim($this->frontendBaseUrl, '/') . '/login?' . http_build_query([
            'forgot' => '1',
            'email' => $profile->getEmail(),
        ]);

        $tpl = $this->mailTemplateContent->getTemplate('auth.password_reset_code', $locale);
        if ($tpl === null) {
            throw $this->vex('tpl_pwd', $locale);
        }

        $vars = [
            'display_name' => $profile->getDisplayName(),
            'reset_code' => strtoupper($code),
            'expires_at' => $expiresText,
            'reset_url' => $resetUrl,
        ];
        $subject = $this->mailTemplateContent->interpolate((string) ($tpl['subject'] ?? ''), $vars);
        $textBody = $this->mailTemplateContent->interpolate((string) ($tpl['text_body'] ?? ''), $vars);
        $htmlCfg = is_array($tpl['html'] ?? null) ? $tpl['html'] : [];

        $email = (new Email())
            ->from($this->mailOutboundSettings->getFromAddressObject())
            ->to($profile->getEmail())
            ->subject($subject)
            ->text($textBody)
            ->html($this->renderFramedMail('password_reset_code.html', [
                'preheader' => $this->mailPreheader($htmlCfg, $vars),
                'accent' => '#e11d48',
                'greeting_word' => (string) ($htmlCfg['greeting_word'] ?? ''),
                'display_name' => $profile->getDisplayName(),
                'headline' => (string) ($htmlCfg['headline'] ?? ''),
                'intro' => (string) ($htmlCfg['intro'] ?? ''),
                'code_label' => (string) ($htmlCfg['code_label'] ?? ''),
                'reset_code' => strtoupper($code),
                'expires_intro' => (string) ($htmlCfg['expires_intro'] ?? ''),
                'expires_at' => $expiresText,
                'cta_label' => (string) ($htmlCfg['cta_label'] ?? ''),
                'link_hint' => (string) ($htmlCfg['link_hint'] ?? ''),
                'reset_url' => $resetUrl,
                'instruction' => (string) ($htmlCfg['instruction'] ?? ''),
                'footer_note' => (string) ($htmlCfg['footer_note'] ?? ''),
            ], [], $locale));

        $this->mailer->send(MailLogKind::stamp($email, 'auth.password_reset_code'));
    }

    private function vex(string $key, string $locale): \RuntimeException
    {
        return new \RuntimeException($this->mailTemplateContent->getApiString('vex.' . $key, $locale));
    }

    private function resolveMailTimezone(string $locale): string
    {
        $raw = getenv('APP_MAIL_TIMEZONE');
        if (is_string($raw) && trim($raw) !== '') {
            return trim($raw);
        }

        return 'Europe/Zurich';
    }

    private function formatMailExpiresAt(\DateTime $expiresAt, string $locale): string
    {
        $timezone = new \DateTimeZone($this->resolveMailTimezone($locale));
        $localized = (clone $expiresAt)->setTimezone($timezone);

        return $localized->format('d.m.Y, H:i') . ' Uhr (' . $timezone->getName() . ')';
    }

    /**
     * @param array<string, mixed> $htmlCfg
     * @param array<string, string> $vars
     */
    private function mailPreheader(array $htmlCfg, array $vars): string
    {
        $raw = (string) ($htmlCfg['preheader'] ?? $htmlCfg['headline'] ?? $htmlCfg['banner_title'] ?? '');

        return $this->mailTemplateContent->interpolate($raw, $vars);
    }

    /**
     * @param array<string, string> $variables
     * @param list<string> $unescapedKeys
     */
    private function renderFramedMail(string $bodyFile, array $variables, array $unescapedKeys, string $locale): string
    {
        $accent = trim((string) ($variables['accent'] ?? ''));
        if ($accent === '' || !preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) {
            $accent = '#10b981';
        }
        $brand = $this->buildBrandHeaderHtml($locale);
        $body = $this->renderHtmlTemplate($bodyFile, $variables, $unescapedKeys, $locale);

        return $this->renderHtmlTemplate('_frame.html', [
            'preheader' => (string) ($variables['preheader'] ?? ''),
            'accent' => $accent,
            'brand_header_html' => $brand,
            'body_html' => $body,
            'footer_note' => (string) ($variables['footer_note'] ?? ''),
        ], ['brand_header_html', 'body_html'], $locale);
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
     * EMC-Badge wie in der App; optional MAILER_BRAND_LOGO_URL (HTTPS).
     */
    private function buildBrandHeaderHtml(string $locale): string
    {
        $alt = htmlspecialchars($this->mailTemplateContent->getSharedString('brand_logo_alt', $locale), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $raw = getenv('MAILER_BRAND_LOGO_URL');
        $url = is_string($raw) ? trim($raw) : '';
        $mark = '<div style="width:44px;height:44px;background:#10b981;border-radius:12px;color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:800;letter-spacing:-0.4px;line-height:44px;text-align:center;">EMC</div>';
        if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) && str_starts_with(strtolower($url), 'https://')) {
            $safe = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $mark = '<img src="' . $safe . '" alt="' . $alt . '" width="44" height="44" style="display:block;border:0;border-radius:12px;" />';
        }

        return '<table role="presentation" cellspacing="0" cellpadding="0"><tr>'
            . '<td style="padding-right:12px;vertical-align:middle;">' . $mark . '</td>'
            . '<td style="vertical-align:middle;font-family:Arial,Helvetica,sans-serif;font-size:20px;font-weight:800;letter-spacing:-0.03em;color:#064e3b;">eMatChef</td>'
            . '</tr></table>';
    }

    /** Login mit Redirect ins Ziel (wie Join-Einladungen). */
    public function buildAppLoginRedirectUrl(string $targetPath): string
    {
        $path = str_starts_with($targetPath, '/') ? $targetPath : '/' . $targetPath;

        return rtrim($this->frontendBaseUrl, '/') . '/login?' . http_build_query([
            'redirect' => $path,
        ], '', '&', \PHP_QUERY_RFC3986);
    }

    public function buildInviteRegisterUrl(string $pendingPath, string $email, Department $department): string
    {
        $path = str_starts_with($pendingPath, '/') ? $pendingPath : '/' . $pendingPath;

        return rtrim($this->frontendBaseUrl, '/') . '/login?' . http_build_query([
            'register' => '1',
            'email' => $email,
            'org_id' => $department->getOrganisationId(),
            'org_name' => $department->getOrganisation()->getName(),
            'dept_name' => $department->getName(),
            'redirect' => $path,
        ], '', '&', \PHP_QUERY_RFC3986);
    }

    public function buildDepartmentMemberAddedAppUrl(string $departmentId, bool $isGrossanlass = false): string
    {
        $targetPath = $isGrossanlass
            ? '/' . $departmentId . '/dashboard'
            : '/' . $departmentId;

        return $this->buildAppLoginRedirectUrl($targetPath);
    }
}
