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
        private string $mailerDsnFromEnv,
        #[Autowire('%kernel.environment%')]
        private string $kernelEnvironment,
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
            'mailer_reply_to_env' => $s['mailer_reply_to_env'],
            'reply_to_address' => $s['reply_to_address'],
            'reply_to_effective' => $s['reply_to_effective'],
            'use_custom_smtp' => $s['use_custom_smtp'],
            'smtp_host' => $s['smtp_host'],
            'smtp_port' => $s['smtp_port'],
            'smtp_user' => $s['smtp_user'],
            'smtp_encryption' => $s['smtp_encryption'],
            'smtp_password_set' => $s['smtp_password_set'],
            'mailer_transport_mode' => $t['mailer_transport_mode'],
            'mail_spool_path' => $t['mail_spool_path'],
            'uses_file_spool' => $t['uses_file_spool'],
            'mail_internal_spool_allowed' => $this->kernelEnvironment !== 'prod',
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
            $savePayload = [
                'from_address' => (string) ($data['from_address'] ?? ''),
                'from_name' => $data['from_name'] ?? null,
                'use_custom_smtp' => !empty($data['use_custom_smtp']),
                'smtp_host' => $data['smtp_host'] ?? '',
                'smtp_port' => $data['smtp_port'] ?? null,
                'smtp_user' => $data['smtp_user'] ?? '',
                'smtp_password' => $data['smtp_password'] ?? null,
                'smtp_encryption' => $data['smtp_encryption'] ?? 'tls',
            ];
            if (array_key_exists('reply_to_address', $data)) {
                $savePayload['reply_to_address'] = $data['reply_to_address'];
            }
            $previewPayload = $this->mailOutboundSettingsStore->buildOutboundPayloadForSave($savePayload);
            if ($this->kernelEnvironment === 'prod') {
                $resolved = $this->mailOutboundSettingsStore->resolveMailTransport($this->mailerDsnFromEnv, $previewPayload);
                if ($resolved['type'] === 'file_spool') {
                    return new JsonResponse([
                        'error' => 'In Produktion ist der lokale Datei-Mailspool nicht erlaubt. Bitte SMTP hier konfigurieren oder MAILER_DSN auf dem Server setzen.',
                    ], 400);
                }
            }
            $this->mailOutboundSettingsStore->save($savePayload);
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
            'mailer_reply_to_env' => $s['mailer_reply_to_env'],
            'reply_to_address' => $s['reply_to_address'],
            'reply_to_effective' => $s['reply_to_effective'],
            'use_custom_smtp' => $s['use_custom_smtp'],
            'smtp_host' => $s['smtp_host'],
            'smtp_port' => $s['smtp_port'],
            'smtp_user' => $s['smtp_user'],
            'smtp_encryption' => $s['smtp_encryption'],
            'smtp_password_set' => $s['smtp_password_set'],
            'mailer_transport_mode' => $t['mailer_transport_mode'],
            'mail_spool_path' => $t['mail_spool_path'],
            'uses_file_spool' => $t['uses_file_spool'],
            'mail_internal_spool_allowed' => $this->kernelEnvironment !== 'prod',
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

        if ($this->kernelEnvironment === 'prod') {
            $r = $this->mailOutboundSettingsStore->resolveMailTransport($this->mailerDsnFromEnv);
            if ($r['type'] === 'file_spool') {
                return new JsonResponse([
                    'error' => 'In Produktion ist kein Versand ueber den lokalen Datei-Spool moeglich. Bitte MAILER_DSN oder SMTP konfigurieren.',
                ], 400);
            }
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

        $fromAddr = $this->mailOutboundSettingsStore->getFromAddressObject()->getAddress();

        try {
            $this->appMailer->send($email);
        } catch (\Throwable $e) {
            $failDetail = 'Fehlgeschlagen: ' . mb_substr($e->getMessage(), 0, 160);
            $this->mailSendLogStore->append('mail.test.failed', $to, $failDetail, $fromAddr);

            return new JsonResponse(['error' => $e->getMessage()], 500);
        }

        $this->mailSendLogStore->append(
            'mail.test',
            $to,
            'eMatChef – Testmail',
            $fromAddr
        );

        return new JsonResponse(['ok' => true]);
    }
}
