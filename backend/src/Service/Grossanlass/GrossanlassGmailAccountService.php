<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassGmailAccount;
use App\Entity\DepartmentGrossanlassInquiry;
use App\Entity\DepartmentGrossanlassMailTemplate;
use App\Entity\User;
use App\Service\Auth\GoogleOAuthException;
use App\Service\Crypto\SecretBox;
use Doctrine\ORM\EntityManagerInterface;

final class GrossanlassGmailAccountService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassAccessService $access,
        private GmailOAuthClient $oauth,
        private GrossanlassGmailApi $gmail,
        private GrossanlassMailMergeService $merge,
        private SecretBox $secrets,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function status(Department $department, User $user): array
    {
        $this->assertManage($department, $user);
        $account = $this->findAccount($department);

        return [
            'oauth_configured' => $this->oauth->isConfigured(),
            'redirect_uri' => $this->oauth->getRedirectUri(),
            'connected' => $account instanceof DepartmentGrossanlassGmailAccount,
            'email' => $account?->getEmail(),
            'connected_at' => $account?->getConnectedAt()->format(\DateTimeInterface::ATOM),
            'settings_path' => '/' . $department->getId() . '/einstellungen/anfragen-email',
        ];
    }

    public function connectUrl(Department $department, User $user): string
    {
        $this->assertManage($department, $user);
        if (!$this->oauth->isConfigured()) {
            throw new GoogleOAuthException('not_configured', 'Google OAuth is not configured');
        }

        return $this->oauth->buildAuthorizationUrl('pending');
    }

    /**
     * @return array<string, mixed>
     */
    public function completeConnect(Department $department, User $user, string $code): array
    {
        $this->assertManage($department, $user);
        $tokens = $this->oauth->exchangeCode($code);
        $account = $this->findAccount($department);
        $isNew = !$account instanceof DepartmentGrossanlassGmailAccount;
        if ($isNew) {
            if (!$tokens['refresh_token']) {
                throw new GoogleOAuthException('token', 'Google hat kein Refresh-Token geliefert. Bitte Zugriff in Google entfernen und erneut verbinden.');
            }
            $account = new DepartmentGrossanlassGmailAccount();
            $account->setDepartment($department);
            $this->entityManager->persist($account);
        }
        $account->setEmail($tokens['email']);
        $account->setConnectedAt(new \DateTime());
        $account->setConnectedByUserId($user->getId());
        $account->setAccessTokenEnc($this->secrets->encrypt($tokens['access_token']));
        $account->setAccessExpiresAt(new \DateTime('+' . max(60, $tokens['expires_in'] - 30) . ' seconds'));
        if ($tokens['refresh_token']) {
            $account->setRefreshTokenEnc($this->secrets->encrypt($tokens['refresh_token']));
        } elseif ($account->getRefreshTokenEnc() === '') {
            throw new GoogleOAuthException('token', 'Google hat kein Refresh-Token geliefert. Bitte Zugriff in Google entfernen und erneut verbinden.');
        }
        $this->entityManager->flush();
        $this->merge->ensureDefaults($department);

        return $this->status($department, $user);
    }

    /**
     * @return array<string, mixed>
     */
    public function disconnect(Department $department, User $user): array
    {
        $this->assertManage($department, $user);
        $account = $this->findAccount($department);
        if ($account instanceof DepartmentGrossanlassGmailAccount) {
            $this->entityManager->remove($account);
            $this->entityManager->flush();
        }

        return $this->status($department, $user);
    }

    /**
     * @param list<string> $ids
     * @return list<array<string, mixed>>
     */
    public function createDrafts(Department $department, User $user, array $ids): array
    {
        $this->assertManage($department, $user);
        $account = $this->requireAccount($department);
        $token = $this->gmail->accessToken($account);
        $this->entityManager->flush();
        $updated = [];
        foreach ($ids as $id) {
            if (!is_string($id) || $id === '') {
                continue;
            }
            $inquiry = $this->entityManager->getRepository(DepartmentGrossanlassInquiry::class)->find($id);
            if (!$inquiry instanceof DepartmentGrossanlassInquiry || $inquiry->getDepartmentId() !== $department->getId()) {
                continue;
            }
            if ($inquiry->getEmail() === '') {
                throw new \InvalidArgumentException('E-Mail fehlt für ' . $inquiry->getName());
            }
            $merged = $this->merge->preview($department, $inquiry, DepartmentGrossanlassMailTemplate::KIND_ANFRAGE);
            $labelIds = $this->ensureInquiryLabels($account, $token, $inquiry);
            $draft = $this->gmail->createDraft(
                $token,
                $inquiry->getEmail(),
                $merged['subject'],
                $merged['body'],
                $inquiry->getId(),
                $labelIds,
            );
            $inquiry->setGmailDraftId($draft['draftId'] !== '' ? $draft['draftId'] : $inquiry->getGmailDraftId());
            $inquiry->setGmailThreadId($draft['threadId'] !== '' ? $draft['threadId'] : $inquiry->getGmailThreadId());
            $inquiry->setGmailMessageId($draft['messageId'] !== '' ? $draft['messageId'] : $inquiry->getGmailMessageId());
            $inquiry->appendThread([
                'who' => 'ok',
                'text' => 'Gmail-Entwurf angelegt.',
            ]);
            $updated[] = $inquiry;
        }
        $this->entityManager->flush();

        return array_map(fn (DepartmentGrossanlassInquiry $row) => $this->serializeInquiry($row), $updated);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function syncInbox(Department $department, User $user): array
    {
        $this->assertManage($department, $user);
        $account = $this->requireAccount($department);
        $token = $this->gmail->accessToken($account);
        $this->entityManager->flush();
        $changed = [];
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassInquiry::class)
            ->findBy(['departmentId' => $department->getId()]);
        foreach ($rows as $inquiry) {
            if (!$inquiry instanceof DepartmentGrossanlassInquiry) {
                continue;
            }
            $did = false;
            if ($inquiry->getGmailDraftId()
                && in_array($inquiry->getStatus(), [
                    DepartmentGrossanlassInquiry::STATUS_ENTWURF,
                    DepartmentGrossanlassInquiry::STATUS_VORSCHLAG,
                ], true)
                && $this->gmail->isDraftGone($token, $inquiry->getGmailDraftId())
            ) {
                $inquiry->setStatus(DepartmentGrossanlassInquiry::STATUS_GESENDET);
                $inquiry->appendThread(['who' => 'ok', 'text' => 'Gmail-Entwurf ist weg — als gesendet gemerkt.']);
                $did = true;
            }
            if (in_array($inquiry->getStatus(), [
                DepartmentGrossanlassInquiry::STATUS_GESENDET,
                DepartmentGrossanlassInquiry::STATUS_ENTWURF,
            ], true)) {
                $reply = $this->findFirmReply($token, $inquiry, $account->getEmail());
                if ($reply !== null) {
                    $inquiry->appendThread(['who' => 'firm', 'text' => $reply]);
                    if ($inquiry->getStatus() === DepartmentGrossanlassInquiry::STATUS_GESENDET) {
                        $inquiry->setStatus(DepartmentGrossanlassInquiry::STATUS_ANTWORT);
                    }
                    $did = true;
                }
            }
            if ($did) {
                $changed[] = $this->serializeInquiry($inquiry);
            }
        }
        $this->entityManager->flush();

        return $changed;
    }

    /**
     * @param list<string> $labelIds
     * @return list<string>
     */
    private function ensureInquiryLabels(
        DepartmentGrossanlassGmailAccount $account,
        string $token,
        DepartmentGrossanlassInquiry $inquiry,
    ): array {
        $root = $this->sanitizeLabel($inquiry->getDepartment()->getName());
        $wanted = [$root, $root . '/Firmenanfragen', $root . '/Status/Wartet auf Antwort'];
        foreach ($inquiry->getCategoryIds() as $category) {
            $wanted[] = $root . '/Firmenanfragen/' . $this->sanitizeLabel($category);
        }
        $map = $account->getLabelMap();
        if ($map === []) {
            foreach ($this->gmail->listLabels($token) as $label) {
                $map[$label['name']] = $label['id'];
            }
        }
        $ids = [];
        foreach ($wanted as $name) {
            if (!isset($map[$name])) {
                try {
                    $map[$name] = $this->gmail->createLabel($token, $name);
                } catch (\Throwable) {
                    continue;
                }
            }
            $ids[] = $map[$name];
        }
        $account->setLabelMap($map);

        return array_values(array_unique($ids));
    }

    private function findFirmReply(string $token, DepartmentGrossanlassInquiry $inquiry, string $okEmail): ?string
    {
        $messages = [];
        if ($inquiry->getGmailThreadId()) {
            try {
                $messages = $this->gmail->listThreadMessages($token, $inquiry->getGmailThreadId());
            } catch (\Throwable) {
                $messages = [];
            }
        }
        if ($messages === []) {
            try {
                $hits = $this->gmail->search($token, '"' . $inquiry->getId() . '"');
                foreach ($hits as $hit) {
                    if ($hit['snippet'] !== '') {
                        $messages[] = [
                            'id' => $hit['id'],
                            'snippet' => $hit['snippet'],
                            'from' => '',
                            'internalDate' => '',
                        ];
                    }
                }
            } catch (\Throwable) {
                return null;
            }
        }
        if (count($messages) < 2) {
            return null;
        }
        $ok = strtolower($okEmail);
        for ($i = count($messages) - 1; $i >= 0; --$i) {
            $from = strtolower($messages[$i]['from']);
            if ($from !== '' && str_contains($from, $ok)) {
                continue;
            }
            $snippet = trim($messages[$i]['snippet']);
            if ($snippet === '') {
                continue;
            }
            foreach ($inquiry->getThread() as $line) {
                if (($line['who'] ?? '') === 'firm' && ($line['text'] ?? '') === $snippet) {
                    return null;
                }
            }

            return $snippet;
        }

        return null;
    }

    public function requireAccount(Department $department): DepartmentGrossanlassGmailAccount
    {
        $account = $this->findAccount($department);
        if (!$account instanceof DepartmentGrossanlassGmailAccount) {
            throw new \RuntimeException('Gmail ist nicht verbunden');
        }

        return $account;
    }

    private function findAccount(Department $department): ?DepartmentGrossanlassGmailAccount
    {
        $account = $this->entityManager->getRepository(DepartmentGrossanlassGmailAccount::class)
            ->find($department->getId());

        return $account instanceof DepartmentGrossanlassGmailAccount ? $account : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeInquiry(DepartmentGrossanlassInquiry $inquiry): array
    {
        return [
            'id' => $inquiry->getId(),
            'name' => $inquiry->getName(),
            'email' => $inquiry->getEmail(),
            'place' => $inquiry->getPlace(),
            'category_ids' => $inquiry->getCategoryIds(),
            'status' => $inquiry->getStatus(),
            'tip_from' => $inquiry->getTipFrom(),
            'tip_wish_id' => $inquiry->getTipWishId(),
            'thread' => $inquiry->getThread(),
            'gmail_draft_id' => $inquiry->getGmailDraftId(),
            'gmail_thread_id' => $inquiry->getGmailThreadId(),
            'gmail_message_id' => $inquiry->getGmailMessageId(),
            'gmail_open_url' => $this->openUrl($inquiry),
            'created_at' => $inquiry->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $inquiry->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function openUrl(DepartmentGrossanlassInquiry $inquiry): ?string
    {
        if ($inquiry->getGmailThreadId()) {
            return 'https://mail.google.com/mail/u/0/#all/' . $inquiry->getGmailThreadId();
        }
        if ($inquiry->getGmailDraftId()) {
            return 'https://mail.google.com/mail/u/0/#drafts';
        }

        return null;
    }

    private function sanitizeLabel(string $name): string
    {
        $clean = trim(str_replace(['/', "\n", "\r"], '-', $name));

        return $clean !== '' ? mb_substr($clean, 0, 80) : 'Grossanlass';
    }

    private function assertManage(Department $department, User $user): void
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canManagePlanung($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für Gmail');
        }
    }
}
