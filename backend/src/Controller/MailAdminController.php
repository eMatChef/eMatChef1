<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\Mail\AppMailer;
use App\Service\Mail\MailOutboundSettingsStore;
use App\Service\Mail\MailSendLogStore;
use App\Service\Mail\MailTemplateContentStore;
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
        private MailTemplateContentStore $mailTemplateContent,
        #[Autowire('%env(MAILER_DSN)%')]
        private string $mailerDsnFromEnv,
    ) {
    }

    private function loc(Request $request): string
    {
        return $this->mailTemplateContent->localeForApiRequest($request);
    }

    #[Route('/settings', name: 'settings_get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getSettings(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => $this->mailTemplateContent->getApiString('ma.unauth', $this->loc($request))], 403);
        }
        if (!$this->isGranted('ROLE_SUPERADMIN')) {
            return new JsonResponse(['error' => $this->mailTemplateContent->getApiString('ma.forbidden', $this->loc($request))], 403);
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
            'mailer_transport_mode' => $t['mailer_transport_mode'],
        ]);
    }

    #[Route('/settings', name: 'settings_patch', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function patchSettings(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => $this->mailTemplateContent->getApiString('ma.unauth', $this->loc($request))], 403);
        }
        if (!$this->isGranted('ROLE_SUPERADMIN')) {
            return new JsonResponse(['error' => $this->mailTemplateContent->getApiString('ma.forbidden_settings', $this->loc($request))], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return new JsonResponse(['error' => $this->mailTemplateContent->getApiString('ma.json_body', $this->loc($request))], 400);
        }

        try {
            $savePayload = [
                'from_address' => (string) ($data['from_address'] ?? ''),
                'from_name' => $data['from_name'] ?? null,
            ];
            if (array_key_exists('reply_to_address', $data)) {
                $savePayload['reply_to_address'] = $data['reply_to_address'];
            }
            $this->mailOutboundSettingsStore->resolveMailTransport($this->mailerDsnFromEnv);
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
            'mailer_transport_mode' => $t['mailer_transport_mode'],
        ]);
    }

    #[Route('/send-log', name: 'send_log', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function sendLog(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => $this->mailTemplateContent->getApiString('ma.unauth', $this->loc($request))], 403);
        }
        if (!$this->isGranted('ROLE_SUPERADMIN')) {
            return new JsonResponse(['error' => $this->mailTemplateContent->getApiString('ma.forbidden', $this->loc($request))], 403);
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
            return new JsonResponse(['error' => $this->mailTemplateContent->getApiString('ma.forbidden', $this->loc($request))], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return new JsonResponse(['error' => $this->mailTemplateContent->getApiString('ma.json_body', $this->loc($request))], 400);
        }
        $to = trim((string) ($data['to'] ?? ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['error' => $this->mailTemplateContent->getApiString('ma.to_invalid', $this->loc($request))], 400);
        }

        try {
            $this->mailOutboundSettingsStore->resolveMailTransport($this->mailerDsnFromEnv);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        $loc = $this->loc($request);
        $ad = $this->mailTemplateContent->getTemplate('admin', $loc) ?? $this->mailTemplateContent->getTemplate('admin', 'de');
        $tm = is_array($ad) && isset($ad['test_mail']) && is_array($ad['test_mail']) ? $ad['test_mail'] : null;
        $mode = (string) ($this->mailOutboundSettingsStore->getTransportSummaryForApi($this->mailerDsnFromEnv)['mailer_transport_mode'] ?? '');
        $subj = $this->mailTemplateContent->interpolate(
            (string) ($tm['subject'] ?? 'eMatChef – Testmail'),
            ['transport_mode' => $mode]
        );
        $body = $this->mailTemplateContent->interpolate(
            (string) ($tm['text_body'] ?? ''),
            ['transport_mode' => $mode]
        );
        $logSubj = $this->mailTemplateContent->interpolate(
            (string) ($tm['log_subject'] ?? $subj),
            ['transport_mode' => $mode]
        );

        $email = (new Email())
            ->from($this->mailOutboundSettingsStore->getFromAddressObject())
            ->to($to)
            ->subject($subj)
            ->text($body);

        $fromAddr = $this->mailOutboundSettingsStore->getFromAddressObject()->getAddress();

        try {
            $this->appMailer->send($email);
        } catch (\Throwable $e) {
            $prefix = $this->mailTemplateContent->getApiString('ma.test_log_fail_prefix', $this->loc($request));
            $failDetail = $prefix . mb_substr($e->getMessage(), 0, 160);
            $this->mailSendLogStore->append('mail.test.failed', $to, $failDetail, $fromAddr);

            return new JsonResponse(['error' => $e->getMessage()], 500);
        }

        $this->mailSendLogStore->append(
            'mail.test',
            $to,
            $logSubj,
            $fromAddr
        );

        return new JsonResponse(['ok' => true]);
    }
}
