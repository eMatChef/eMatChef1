<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\Mail\AppMailer;
use App\Service\Mail\MailOutboundSettingsStore;
use App\Service\Mail\MailSendLogStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/mail', name: 'api_mail_')]
class MailAdminController extends AbstractController
{
    public function __construct(
        private MailOutboundSettingsStore $mailOutboundSettingsStore,
        private MailSendLogStore $mailSendLogStore,
        private AppMailer $appMailer,
        #[Autowire('%env(MAILER_DSN)%')]
        private string $mailerDsnFromEnv
    ) {
    }

    #[Route('/settings', name: 'settings_get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getSettings(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 403);
        }
        if (!$this->isGranted('ROLE_SUPERADMIN')) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        $s = $this->mailOutboundSettingsStore->getSettingsForApi();
        $t = $this->mailOutboundSettingsStore->getTransportSummaryForApi($this->mailerDsnFromEnv);

        return new JsonResponse([
            'from_address' => $s['from_address'],
            'from_name' => $s['from_name'],
            'uses_file' => $s['uses_file'],
            'env_fallback_address' => $this->mailOutboundSettingsStore->getEnvDefaultAddress(),
            'use_custom_smtp' => $s['use_custom_smtp'],
            'smtp_host' => $s['smtp_host'],
            'smtp_port' => $s['smtp_port'],
            'smtp_user' => $s['smtp_user'],
            'smtp_encryption' => $s['smtp_encryption'],
            'smtp_password_set' => $s['smtp_password_set'],
            'mailer_transport_mode' => $t['mailer_transport_mode'],
            'mail_spool_path' => $t['mail_spool_path'],
            'uses_file_spool' => $t['uses_file_spool'],
        ]);
    }

    #[Route('/settings', name: 'settings_patch', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function patchSettings(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 403);
        }
        if (!$this->isGranted('ROLE_SUPERADMIN')) {
            return new JsonResponse(['error' => 'Nur Superadmin kann die E-Mail-Einstellungen aendern'], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return new JsonResponse(['error' => 'Ungueltiger JSON-Body'], 400);
        }

        try {
            $this->mailOutboundSettingsStore->save([
                'from_address' => (string) ($data['from_address'] ?? ''),
                'from_name' => $data['from_name'] ?? null,
                'use_custom_smtp' => !empty($data['use_custom_smtp']),
                'smtp_host' => $data['smtp_host'] ?? '',
                'smtp_port' => $data['smtp_port'] ?? null,
                'smtp_user' => $data['smtp_user'] ?? '',
                'smtp_password' => $data['smtp_password'] ?? null,
                'smtp_encryption' => $data['smtp_encryption'] ?? 'tls',
            ]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }

        $s = $this->mailOutboundSettingsStore->getSettingsForApi();
        $t = $this->mailOutboundSettingsStore->getTransportSummaryForApi($this->mailerDsnFromEnv);

        return new JsonResponse([
            'from_address' => $s['from_address'],
            'from_name' => $s['from_name'],
            'uses_file' => true,
            'env_fallback_address' => $this->mailOutboundSettingsStore->getEnvDefaultAddress(),
            'use_custom_smtp' => $s['use_custom_smtp'],
            'smtp_host' => $s['smtp_host'],
            'smtp_port' => $s['smtp_port'],
            'smtp_user' => $s['smtp_user'],
            'smtp_encryption' => $s['smtp_encryption'],
            'smtp_password_set' => $s['smtp_password_set'],
            'mailer_transport_mode' => $t['mailer_transport_mode'],
            'mail_spool_path' => $t['mail_spool_path'],
            'uses_file_spool' => $t['uses_file_spool'],
        ]);
    }

    #[Route('/send-log', name: 'send_log', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function sendLog(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Nicht authentifiziert'], 403);
        }
        if (!$this->isGranted('ROLE_SUPERADMIN')) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        $limit = (int) $request->query->get('limit', 100);

        return new JsonResponse([
            'entries' => $this->mailSendLogStore->getRecent($limit),
        ]);
    }

    #[Route('/test-send', name: 'test_send', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function testSend(Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_SUPERADMIN')) {
            return new JsonResponse(['error' => 'Keine Berechtigung'], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return new JsonResponse(['error' => 'Ungueltiger JSON-Body'], 400);
        }
        $to = trim((string) ($data['to'] ?? ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['error' => 'Gueltige Ziel-E-Mail (to) erforderlich'], 400);
        }

        $email = (new Email())
            ->from($this->mailOutboundSettingsStore->getFromAddressObject())
            ->to($to)
            ->subject('eMatChef – Testmail')
            ->text(
                "Dies ist eine Testmail.\n\n" .
                "Wenn du diese Nachricht erhalten hast, ist der konfigurierte Versandweg aktiv.\n" .
                'Modus: ' . $this->mailOutboundSettingsStore->getTransportSummaryForApi($this->mailerDsnFromEnv)['mailer_transport_mode'] . "\n"
            );

        try {
            $this->appMailer->send($email);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }

        $this->mailSendLogStore->append('mail.test', $to, 'eMatChef – Testmail');

        return new JsonResponse(['ok' => true]);
    }
}
