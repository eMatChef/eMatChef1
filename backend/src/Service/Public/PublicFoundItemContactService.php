<?php

namespace App\Service\Public;

use App\Entity\Department;
use App\Entity\PublicFoundItemMessage;
use App\Service\Mail\AppMailer;
use App\Service\Mail\MailOutboundSettingsStore;
use App\Service\Mail\MailSendLogStore;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mime\Email;

class PublicFoundItemContactService
{
    public function __construct(
        private PublicCodeService $publicCodeService,
        private AppMailer $mailer,
        private MailOutboundSettingsStore $mailOutboundSettings,
        private MailSendLogStore $mailSendLog,
        private EntityManagerInterface $entityManager,
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

        $delivery = $this->publicCodeService->getPublicFoundContactDelivery($departmentId);
        $sendEmail = in_array($delivery, ['email', 'both'], true);
        $storeInApp = in_array($delivery, ['in_app', 'both'], true);

        if (!$sendEmail && !$storeInApp) {
            return ['error' => 'Kontaktformular ist nicht konfiguriert.', 'status' => 400];
        }

        $to = $this->publicCodeService->getPublicRecipientEmailForDepartmentId($departmentId);
        if ($sendEmail && (!$to || trim((string) $to) === '')) {
            return ['error' => 'Fuer E-Mail-Versand ist eine Kontakt-E-Mail erforderlich.', 'status' => 400];
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
            ? $this->publicCodeService->buildBatchPublicUrl($publicCode)
            : $this->publicCodeService->buildMaterialPublicUrl($publicCode);

        $materialId = (string) ($lookup['material']['id'] ?? '');
        $batchId = $entityType === 'batch' ? (string) (($lookup['batch']['id'] ?? '') ?: '') : '';

        if ($storeInApp) {
            $this->persistInAppMessage(
                $departmentId,
                $entityType,
                $materialId !== '' ? $materialId : null,
                $batchId !== '' ? $batchId : null,
                $publicCode,
                $materialName,
                $deptName,
                $serialLine !== '' ? rtrim($serialLine) : null,
                $message,
                $senderName !== '' ? $senderName : null,
                $senderEmail !== '' ? $senderEmail : null,
                $publicUrl
            );
        }

        if ($sendEmail && $to) {
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
                ->from($this->mailOutboundSettings->getFromAddressObject())
                ->to($to)
                ->subject($subject)
                ->text($body);

            if ($senderEmail !== '' && filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
                $email->replyTo($senderEmail);
            }

            $this->mailer->send($email);
            $this->mailSendLog->append(
                'public.found_item_contact',
                $to,
                $subject,
                $this->mailOutboundSettings->getFromAddressObject()->getAddress()
            );
        }

        return ['ok' => true];
    }

    private function persistInAppMessage(
        string $departmentId,
        string $entityType,
        ?string $materialId,
        ?string $batchId,
        string $publicCode,
        string $materialName,
        string $departmentName,
        ?string $serialLine,
        string $message,
        ?string $senderName,
        ?string $senderEmail,
        string $publicUrl,
    ): void {
        $dept = $this->entityManager->getRepository(Department::class)->find($departmentId);
        if (!$dept) {
            return;
        }

        $msg = new PublicFoundItemMessage();
        $msg->setId(IdGenerator::generate12UniqueWithPrefix($this->entityManager, PublicFoundItemMessage::class, 'pf'));
        $msg->setDepartment($dept);
        $msg->setEntityType($entityType);
        $msg->setMaterialId($materialId);
        $msg->setBatchId($batchId);
        $msg->setPublicCode($publicCode);
        $msg->setMaterialName($materialName);
        $msg->setDepartmentName($departmentName);
        $msg->setSerialLine($serialLine);
        $msg->setMessage($message);
        $msg->setSenderName($senderName);
        $msg->setSenderEmail($senderEmail);
        $msg->setPublicUrl($publicUrl);
        $msg->setCreatedAt(new \DateTime());
        $msg->setStatus(PublicFoundItemMessage::STATUS_OPEN);

        $this->entityManager->persist($msg);
        $this->entityManager->flush();
    }
}
