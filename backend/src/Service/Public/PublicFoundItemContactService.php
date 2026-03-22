<?php

namespace App\Service\Public;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class PublicFoundItemContactService
{
    public function __construct(
        private PublicCodeService $publicCodeService,
        private MailerInterface $mailer,
        #[Autowire('%env(MAILER_FROM)%')] private string $fromAddress,
        #[Autowire('%env(APP_FRONTEND_URL)%')] private string $publicFrontendUrl
    ) {
    }

    /**
     * @return array{ok: true}|array{error: string, status: int}
     */
    public function handle(array $payload): array
    {
        // Honeypot (Spam-Bots)
        $trap = trim((string) ($payload['website'] ?? $payload['url'] ?? ''));
        if ($trap !== '') {
            return ['ok' => true];
        }

        $entityType = strtolower(trim((string) ($payload['entity_type'] ?? '')));
        $publicCode = trim((string) ($payload['public_code'] ?? ''));
        $message = trim((string) ($payload['message'] ?? ''));
        $senderName = trim((string) ($payload['sender_name'] ?? ''));
        $senderEmail = trim((string) ($payload['sender_email'] ?? ''));

        if ($publicCode === '') {
            return ['error' => 'public_code fehlt', 'status' => 400];
        }
        if (!in_array($entityType, ['material', 'batch'], true)) {
            return ['error' => 'entity_type ungueltig', 'status' => 400];
        }
        if ($message === '') {
            return ['error' => 'Nachricht fehlt', 'status' => 400];
        }
        if (mb_strlen($message) < 5) {
            return ['error' => 'Nachricht ist zu kurz', 'status' => 400];
        }
        if (mb_strlen($message) > 4000) {
            return ['error' => 'Nachricht ist zu lang', 'status' => 400];
        }
        if ($senderName !== '' && mb_strlen($senderName) > 120) {
            return ['error' => 'Name ist zu lang', 'status' => 400];
        }
        if ($senderEmail !== '' && !filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            return ['error' => 'E-Mail-Adresse ungueltig', 'status' => 400];
        }

        $lookup = $entityType === 'batch'
            ? $this->publicCodeService->resolveBatchByPublicCode($publicCode)
            : $this->publicCodeService->resolveMaterialByPublicCode($publicCode);

        if ($lookup === null) {
            return ['error' => 'Public-Code nicht gefunden oder nicht aktiv', 'status' => 404];
        }

        $publicUi = $lookup['public_ui'] ?? [];
        if (($publicUi['show_contact_form'] ?? true) === false) {
            return ['error' => 'Kontaktformular ist fuer diese Abteilung deaktiviert.', 'status' => 403];
        }

        $departmentId = (string) ($lookup['department']['id'] ?? '');
        if ($departmentId === '') {
            return ['error' => 'Department ungueltig', 'status' => 400];
        }

        $to = $this->publicCodeService->getPublicRecipientEmailForDepartmentId($departmentId);
        if (!$to || trim((string) $to) === '') {
            return ['error' => 'Fuer diese Abteilung ist keine Kontakt-E-Mail hinterlegt.', 'status' => 400];
        }

        $materialName = (string) ($lookup['material']['name'] ?? '');
        $deptName = (string) ($lookup['department']['name'] ?? '');
        $serialLine = '';
        if (($lookup['entity_type'] ?? '') === 'batch') {
            $b = $lookup['batch'] ?? [];
            $serial = trim((string) ($b['serial_number'] ?? ''));
            $label = trim((string) ($b['label'] ?? ''));
            $bid = trim((string) ($b['id'] ?? ''));
            $serialLine = 'Serie: ' . ($serial !== '' ? $serial : ($label !== '' ? $label : $bid)) . "\n";
        }

        $publicUrl = $entityType === 'batch'
            ? rtrim($this->publicFrontendUrl, '/') . '/i/b/' . rawurlencode($publicCode)
            : rtrim($this->publicFrontendUrl, '/') . '/i/m/' . rawurlencode($publicCode);

        $subject = '[eMatChef] Hinweis: Artikel gefunden – ' . $materialName;

        $body = "Jemand hat ueber den oeffentlichen QR-Link einen Hinweis gesendet.\n\n";
        $body .= "Artikel: {$materialName}\n";
        $body .= "Abteilung: {$deptName}\n";
        if ($serialLine !== '') {
            $body .= $serialLine;
        }
        $body .= "Public-Link: {$publicUrl}\n";
        $body .= "Public-Code: {$publicCode}\n\n";
        $body .= "Nachricht:\n{$message}\n\n";
        $body .= "Absender:\n";
        $body .= $senderName !== '' ? "Name: {$senderName}\n" : "Name: (nicht angegeben)\n";
        $body .= $senderEmail !== '' ? "E-Mail: {$senderEmail}\n" : "E-Mail: (nicht angegeben)\n";

        $email = (new Email())
            ->from($this->fromAddress)
            ->to($to)
            ->subject($subject)
            ->text($body);

        if ($senderEmail !== '' && filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            $email->replyTo($senderEmail);
        }

        $this->mailer->send($email);

        return ['ok' => true];
    }
}
