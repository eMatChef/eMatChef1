<?php

namespace App\Service\Public;

use App\Entity\Department;
use App\Service\InboxMessageService;
use App\Service\Mail\AppMailer;
use App\Service\Mail\MailLogKind;
use App\Service\Mail\MailOutboundSettingsStore;
use App\Service\Mail\MailTemplateContentStore;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mime\Email;

class PublicFoundItemContactService
{
    public function __construct(
        private PublicCodeService $publicCodeService,
        private AppMailer $mailer,
        private MailOutboundSettingsStore $mailOutboundSettings,
        private MailTemplateContentStore $mailTemplateContent,
        private EntityManagerInterface $entityManager,
        private InboxMessageService $inboxMessages,
    ) {
    }

    /**
     * @return array{ok: true}|array{error: string, status: int}
     */
    public function handle(array $payload, string $errorLocale = 'de'): array
    {
        $api = fn (string $k): string => $this->mailTemplateContent->getApiString('pfd.' . $k, $errorLocale);

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
            return ['error' => $api('public_code'), 'status' => 400];
        }
        if (!in_array($entityType, ['material', 'batch', 'activity', 'workshop'], true)) {
            return ['error' => $api('entity_type'), 'status' => 400];
        }
        if ($message === '') {
            return ['error' => $api('message_empty'), 'status' => 400];
        }
        if (mb_strlen($message) < 5) {
            return ['error' => $api('message_short'), 'status' => 400];
        }
        if (mb_strlen($message) > 4000) {
            return ['error' => $api('message_long'), 'status' => 400];
        }
        if ($senderName !== '' && mb_strlen($senderName) > 120) {
            return ['error' => $api('name_long'), 'status' => 400];
        }
        if ($senderEmail !== '' && !filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            return ['error' => $api('email_invalid'), 'status' => 400];
        }

        $lookup = match ($entityType) {
            'batch' => $this->publicCodeService->resolveBatchByPublicCode($publicCode),
            'activity' => $this->publicCodeService->resolveActivityByPublicCode($publicCode),
            'workshop' => $this->publicCodeService->resolveWorkshopByPublicCode($publicCode),
            default => $this->publicCodeService->resolveMaterialByPublicCode($publicCode),
        };

        if ($lookup === null) {
            return ['error' => $api('code_not_found'), 'status' => 404];
        }

        $publicUi = $lookup['public_ui'] ?? [];
        if (($publicUi['show_contact_form'] ?? true) === false) {
            return ['error' => $api('form_disabled'), 'status' => 403];
        }

        $departmentId = (string) ($lookup['department']['id'] ?? '');
        if ($departmentId === '') {
            return ['error' => $api('department'), 'status' => 400];
        }

        $delivery = $this->publicCodeService->getPublicFoundContactDelivery($departmentId);
        $sendEmail = in_array($delivery, ['email', 'both'], true);
        $storeInApp = in_array($delivery, ['in_app', 'both'], true);

        if (!$sendEmail && !$storeInApp) {
            return ['error' => $api('not_configured'), 'status' => 400];
        }

        $to = $this->publicCodeService->getPublicRecipientEmailForDepartmentId($departmentId);
        if ($sendEmail && (!$to || trim((string) $to) === '')) {
            return ['error' => $api('to_required'), 'status' => 400];
        }

        $materialName = match ($entityType) {
            'activity' => (string) ($lookup['activity']['name'] ?? ''),
            'workshop' => (string) ($lookup['workshop']['title'] ?? $lookup['workshop']['material_name'] ?? ''),
            default => (string) ($lookup['material']['name'] ?? ''),
        };
        $deptName = (string) ($lookup['department']['name'] ?? '');
        $locMail = $this->mailTemplateContent->resolveMailLocale(null);
        $tplForLines = $this->mailTemplateContent->getTemplate('public.found_item_contact', $locMail) ?? [];
        $serialLine = '';
        if ($entityType === 'activity') {
            $act = $lookup['activity'] ?? [];
            $type = trim((string) ($act['type'] ?? ''));
            $no = $act['no'] ?? null;
            $period = trim(implode(' – ', array_filter([
                $act['usage_start'] ?? $act['planning_start'] ?? '',
                $act['usage_end'] ?? $act['planning_end'] ?? '',
            ])));
            $parts = array_filter([
                $type !== '' ? $type : null,
                $no !== null && $no !== '' ? '#' . $no : null,
                $period !== '' ? $period : null,
            ]);
            if ($parts !== []) {
                $serialLine = implode(' · ', $parts) . "\n";
            }
        } elseif ($entityType === 'workshop') {
            $ws = $lookup['workshop'] ?? [];
            $parts = array_filter([
                trim((string) ($ws['type'] ?? '')),
                trim((string) ($ws['status'] ?? '')),
                trim((string) ($ws['material_name'] ?? '')),
            ]);
            if ($parts !== []) {
                $serialLine = implode(' · ', $parts) . "\n";
            }
        } elseif (($lookup['entity_type'] ?? '') === 'batch') {
            $b = $lookup['batch'] ?? [];
            $serial = trim((string) ($b['serial_number'] ?? ''));
            $label = trim((string) ($b['label'] ?? ''));
            $bid = trim((string) ($b['id'] ?? ''));
            $sv = $serial !== '' ? $serial : ($label !== '' ? $label : $bid);
            $serialLine = $this->mailTemplateContent->interpolate(
                (string) ($tplForLines['line_serial'] ?? "Serie: {{serial_value}}\n"),
                ['serial_value' => $sv]
            );
        }

        $publicUrl = $this->publicCodeService->resolvePublicUrlFromLookup($entityType, $lookup, $publicCode);
        if ($publicUrl === '') {
            return ['error' => $api('code_not_found'), 'status' => 404];
        }

        $materialId = match ($entityType) {
            'activity' => (string) ($lookup['activity']['id'] ?? ''),
            'workshop' => (string) ($lookup['workshop']['id'] ?? ''),
            default => (string) ($lookup['material']['id'] ?? ''),
        };
        $batchId = $entityType === 'batch' ? (string) (($lookup['batch']['id'] ?? '') ?: '') : '';

        $publicFoundMailTpl = null;
        if ($sendEmail && $to) {
            $localeMail = $this->mailTemplateContent->resolveMailLocale(null);
            $publicFoundMailTpl = $this->mailTemplateContent->getTemplate('public.found_item_contact', $localeMail);
            if ($publicFoundMailTpl === null) {
                return ['error' => $api('template_missing'), 'status' => 500];
            }
        }

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

        if ($sendEmail && $to && $publicFoundMailTpl !== null) {
            $emptyL = (string) ($publicFoundMailTpl['sender_value_empty'] ?? '(nicht angegeben)');
            $l1 = $this->mailTemplateContent->interpolate(
                (string) ($publicFoundMailTpl['sender_name_line'] ?? "Name: {{value}}\n"),
                ['value' => $senderName !== '' ? $senderName : $emptyL]
            );
            $l2 = $this->mailTemplateContent->interpolate(
                (string) ($publicFoundMailTpl['sender_email_line'] ?? "E-Mail: {{value}}\n"),
                ['value' => $senderEmail !== '' ? $senderEmail : $emptyL]
            );
            $senderLines = $l1 . $l2;
            $vars = [
                'material_name' => $materialName,
                'department_name' => $deptName,
                'serial_block' => $serialLine !== '' ? $serialLine : '',
                'public_url' => $publicUrl,
                'public_code' => $publicCode,
                'message' => $message,
                'sender_lines' => $senderLines,
            ];
            $subject = $this->mailTemplateContent->interpolate((string) ($publicFoundMailTpl['subject'] ?? ''), $vars);
            $body = $this->mailTemplateContent->interpolate((string) ($publicFoundMailTpl['text_body'] ?? ''), $vars);

            $email = (new Email())
                ->from($this->mailOutboundSettings->getFromAddressObject())
                ->to($to)
                ->subject($subject)
                ->text($body);

            if ($senderEmail !== '' && filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
                $email->replyTo($senderEmail);
            }

            $this->mailer->send(MailLogKind::stamp($email, 'public.found_item_contact'));
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

        $this->inboxMessages->createQrFoundMessage(
            $dept,
            $entityType,
            $materialId,
            $batchId,
            $publicCode,
            $materialName,
            $departmentName,
            $serialLine,
            $message,
            $senderName,
            $senderEmail,
            $publicUrl,
        );
    }
}
