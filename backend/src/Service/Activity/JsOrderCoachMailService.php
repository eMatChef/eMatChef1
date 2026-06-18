<?php

declare(strict_types=1);

namespace App\Service\Activity;

use App\Entity\Activity;
use App\Entity\ActivityJsOrder;
use App\Entity\User;
use App\Service\Activity\ActivityJsOrderPdfStorageService;
use App\Service\JsOrderPrefillService;
use App\Service\Mail\AppMailer;
use App\Service\Mail\MailOutboundSettingsStore;
use App\Service\Mail\MailSendLogStore;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Versendet J+S-Bestell-PDF an den J+S-Coach (Department-Default oder Formular).
 */
class JsOrderCoachMailService
{
    public function __construct(
        private AppMailer $mailer,
        private MailOutboundSettingsStore $mailOutboundSettings,
        private MailSendLogStore $mailSendLog,
        private ActivityJsOrderPdfStorageService $pdfStorage,
        private JsOrderPrefillService $prefillService,
    ) {
    }

    public function resolveCoachEmail(ActivityJsOrder $order, Activity $activity): ?string
    {
        $formData = $order->getFormData() ?? [];
        $block2 = \is_array($formData['block2'] ?? null) ? $formData['block2'] : [];
        $fromForm = trim((string) ($block2['coach_email'] ?? ''));
        if ($fromForm !== '' && filter_var($fromForm, FILTER_VALIDATE_EMAIL)) {
            return $fromForm;
        }

        $deptDefaults = $this->prefillService->loadDepartmentJsDefaults($activity->getDepartmentId());
        $fromDept = trim((string) ($deptDefaults['js.default_coach_email'] ?? ''));
        if ($fromDept !== '' && filter_var($fromDept, FILTER_VALIDATE_EMAIL)) {
            return $fromDept;
        }

        return null;
    }

    public function sendToCoach(ActivityJsOrder $order, Activity $activity, User $sender): void
    {
        $coachEmail = $this->resolveCoachEmail($order, $activity);
        if ($coachEmail === null) {
            throw new \InvalidArgumentException('Keine gültige Coach-E-Mail — in Einstellungen → Aktivitäten oder im Formular hinterlegen.');
        }

        $mediaId = $order->getGeneratedPdfMediaId();
        if ($mediaId === null || $mediaId === '') {
            throw new \InvalidArgumentException('PDF fehlt — zuerst «PDF erzeugen».');
        }

        $filename = $mediaId . '.pdf';
        $path = $this->pdfStorage->resolveFilePath(
            $activity->getId(),
            (string) $order->getId(),
            $activity->getDepartmentId(),
            $filename,
        );

        $formData = $order->getFormData() ?? [];
        $block2 = \is_array($formData['block2'] ?? null) ? $formData['block2'] : [];
        $coachName = trim(
            ((string) ($block2['coach_first_name'] ?? '')) . ' ' . ((string) ($block2['coach_last_name'] ?? '')),
        );
        $activityName = trim($activity->getName());
        $deliveryDate = trim((string) ($block2['delivery_date'] ?? ''));

        $subject = 'J+S-Leihmaterialbestellung: ' . ($activityName !== '' ? $activityName : 'Lager/Event');
        $textBody = implode("\n", array_filter([
            $coachName !== '' ? 'Guten Tag ' . $coachName : 'Guten Tag',
            '',
            'Anbei die J+S-Leihmaterialbestellung für «' . ($activityName !== '' ? $activityName : 'Lager/Event') . '».',
            $deliveryDate !== '' ? 'Lieferdatum: ' . $deliveryDate : null,
            '',
            'Versendet über eMatChef.',
            '',
            'Freundliche Grüsse',
            $sender->getProfile()?->getDisplayName() ?? 'eMatChef',
        ]));

        $email = (new Email())
            ->from($this->mailOutboundSettings->getFromAddressObject())
            ->to(new Address($coachEmail, $coachName !== '' ? $coachName : $coachEmail))
            ->subject($subject)
            ->text($textBody)
            ->attachFromPath($path, $filename, 'application/pdf');

        $this->mailer->send($email);

        $fromAddr = $this->mailOutboundSettings->getFromAddressObject()->getAddress();
        $this->mailSendLog->append('js_order.coach', $coachEmail, $subject, $fromAddr);
    }
}
