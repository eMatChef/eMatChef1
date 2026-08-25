<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\DepartmentGrossanlassGmailAccount;
use App\Entity\DepartmentGrossanlassGmailUnmatched;
use App\Entity\DepartmentGrossanlassInquiry;
use App\Entity\DepartmentGrossanlassMailTemplate;
use App\Entity\User;
use App\Service\Auth\GoogleOAuthException;
use App\Service\Crypto\SecretBox;
use App\Util\GrossanlassIdGenerator;
use Doctrine\ORM\EntityManagerInterface;

final class GrossanlassGmailAccountService
{
    /** @var list<string> */
    public const REPLY_KINDS = [
        DepartmentGrossanlassMailTemplate::KIND_ZUSAGE_OK,
        DepartmentGrossanlassMailTemplate::KIND_DANK_ABSAGE,
        DepartmentGrossanlassMailTemplate::KIND_NICHT_GENOMMEN,
        DepartmentGrossanlassMailTemplate::KIND_NACHFASSEN,
        DepartmentGrossanlassMailTemplate::KIND_NEHMEN,
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassAccessService $access,
        private GmailOAuthClient $oauth,
        private GrossanlassGmailApi $gmail,
        private GrossanlassMailMergeService $merge,
        private SecretBox $secrets,
        private GrossanlassProcurementService $procurement,
        private GrossanlassCommitmentService $commitments,
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
            $labelIds = $this->ensureLabelIds(
                $account,
                $token,
                $this->merge->gmailLabelNames($department, $inquiry, GrossanlassGmailRouting::STATUS_WAITING),
            );
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
            if ($inquiry->getStatus() === DepartmentGrossanlassInquiry::STATUS_VORSCHLAG) {
                $inquiry->setStatus(DepartmentGrossanlassInquiry::STATUS_ENTWURF);
            }
            $inquiry->appendThread([
                'who' => 'ok',
                'text' => 'Gmail-Entwurf angelegt.',
            ]);
            $this->procurement->freezeAskedFromInquiry($department, $inquiry);
            $updated[] = $inquiry;
        }
        $this->entityManager->flush();

        return array_map(fn (DepartmentGrossanlassInquiry $row) => $this->serializeInquiry($row), $updated);
    }

    /**
     * @return array{updated: list<array<string, mixed>>, unmatched: list<array<string, mixed>>, ignored: int}
     */
    public function syncInbox(Department $department, User $user): array
    {
        $this->assertManage($department, $user);
        $account = $this->requireAccount($department);
        $token = $this->gmail->accessToken($account);
        $this->entityManager->flush();

        $inquiries = $this->entityManager->getRepository(DepartmentGrossanlassInquiry::class)
            ->findBy(['departmentId' => $department->getId()]);
        $changed = [];
        $ignored = 0;
        $seen = $this->knownMessageIds($department, $inquiries);

        foreach ($inquiries as $inquiry) {
            if (!$inquiry instanceof DepartmentGrossanlassInquiry) {
                continue;
            }
            $did = false;
            $messages = [];
            if ($inquiry->getGmailThreadId()) {
                try {
                    $messages = $this->gmail->listThreadMessages($token, $inquiry->getGmailThreadId());
                } catch (\Throwable) {
                    $messages = [];
                }
            }
            $draftThere = false;
            if ($inquiry->getGmailDraftId()) {
                $draftThere = !$this->gmail->isDraftGone($token, $inquiry->getGmailDraftId());
                if (!$draftThere) {
                    $inquiry->setGmailDraftId(null);
                    $did = true;
                }
            }
            if ($this->applyMailboxStatus($department, $account, $token, $inquiry, $messages, $draftThere)) {
                $did = true;
            }
            foreach ($messages as $message) {
                $result = $this->ingestMessage($department, $account, $token, $message, $inquiry, $seen, $user);
                if ($result === 'ignored') {
                    ++$ignored;
                } elseif ($result === 'attached') {
                    $did = true;
                }
            }
            if ($did) {
                $changed[$inquiry->getId()] = $inquiry;
            }
        }

        try {
            $inboxIds = $this->gmail->listMessageIds($token, 'in:inbox newer_than:21d', 40);
        } catch (\Throwable) {
            $inboxIds = [];
        }
        foreach ($inboxIds as $messageId) {
            if (isset($seen[$messageId])) {
                continue;
            }
            try {
                $message = $this->gmail->getMessage($token, $messageId);
            } catch (\Throwable) {
                continue;
            }
            $matched = $this->resolveInquiry($department, $inquiries, $message);
            $result = $this->ingestMessage($department, $account, $token, $message, $matched, $seen, $user);
            if ($result === 'ignored') {
                ++$ignored;
            } elseif ($result === 'attached' && $matched instanceof DepartmentGrossanlassInquiry) {
                $changed[$matched->getId()] = $matched;
            }
        }

        $this->entityManager->flush();

        return [
            'updated' => array_values(array_map(
                fn (DepartmentGrossanlassInquiry $row) => $this->serializeInquiry($row),
                $changed,
            )),
            'unmatched' => $this->listUnmatched($department, $user),
            'ignored' => $ignored,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listUnmatched(Department $department, User $user): array
    {
        $this->assertManage($department, $user);
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassGmailUnmatched::class)
            ->findBy(['departmentId' => $department->getId(), 'discardedAt' => null], ['receivedAt' => 'DESC']);

        return array_map(fn (DepartmentGrossanlassGmailUnmatched $row) => $this->serializeUnmatched($row), $rows);
    }

    /**
     * @return array{inquiry: array<string, mixed>, unmatched: list<array<string, mixed>>}
     */
    public function assignUnmatched(Department $department, User $user, string $unmatchedId, string $inquiryId): array
    {
        $this->assertManage($department, $user);
        $row = $this->findUnmatched($department, $unmatchedId);
        $inquiry = $this->entityManager->getRepository(DepartmentGrossanlassInquiry::class)->find($inquiryId);
        if (!$inquiry instanceof DepartmentGrossanlassInquiry || $inquiry->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Anfrage nicht gefunden');
        }
        $account = $this->requireAccount($department);
        $token = $this->gmail->accessToken($account);
        $this->entityManager->flush();
        $message = [
            'id' => $row->getGmailMessageId(),
            'threadId' => $row->getGmailThreadId(),
            'from' => $row->getFromName() !== ''
                ? $row->getFromName() . ' <' . $row->getFromEmail() . '>'
                : $row->getFromEmail(),
            'subject' => $row->getSubject(),
            'body' => $row->getBody(),
            'internalDate' => (string) ($row->getReceivedAt()->getTimestamp() * 1000),
            'headers' => [],
            'snippet' => mb_substr($row->getBody(), 0, 140),
            'messageIdHeader' => '',
        ];
        $seen = [];
        $this->attachFirmMessage($department, $account, $token, $inquiry, $message, $seen);
        $this->entityManager->remove($row);
        $this->entityManager->flush();

        return [
            'inquiry' => $this->serializeInquiry($inquiry),
            'unmatched' => $this->listUnmatched($department, $user),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function discardUnmatched(Department $department, User $user, string $unmatchedId): array
    {
        $this->assertManage($department, $user);
        $row = $this->findUnmatched($department, $unmatchedId);
        $row->setDiscardedAt(new \DateTime());
        $this->entityManager->flush();

        return $this->listUnmatched($department, $user);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{inquiry: array<string, mixed>, unmatched: list<array<string, mixed>>}
     */
    public function unmatchedToInquiry(Department $department, User $user, string $unmatchedId, array $data): array
    {
        $this->assertManage($department, $user);
        $row = $this->findUnmatched($department, $unmatchedId);
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $name = $row->getFromName() !== '' ? $row->getFromName() : $row->getFromEmail();
        }
        if ($name === '') {
            throw new \InvalidArgumentException('Name ist erforderlich');
        }
        $email = strtolower(trim((string) ($data['email'] ?? $row->getFromEmail())));
        $inquiry = new DepartmentGrossanlassInquiry();
        $inquiry->setId(GrossanlassIdGenerator::unique(
            $this->entityManager,
            GrossanlassIdGenerator::INQUIRY,
            DepartmentGrossanlassInquiry::class,
        ));
        $inquiry->setDepartment($department);
        $inquiry->setName($name);
        $inquiry->setEmail($email);
        $inquiry->setPlace(trim((string) ($data['place'] ?? '')));
        $inquiry->setStatus(DepartmentGrossanlassInquiry::STATUS_ANTWORT);
        $this->entityManager->persist($inquiry);
        $this->entityManager->flush();

        return $this->assignUnmatched($department, $user, $unmatchedId, $inquiry->getId());
    }

    /**
     * @return array<string, mixed>
     */
    public function createReplyDraft(Department $department, User $user, string $inquiryId, string $kind): array
    {
        $this->assertManage($department, $user);
        if (!in_array($kind, self::REPLY_KINDS, true)) {
            throw new \InvalidArgumentException('Unbekannte Antwort-Vorlage');
        }
        $inquiry = $this->entityManager->getRepository(DepartmentGrossanlassInquiry::class)->find($inquiryId);
        if (!$inquiry instanceof DepartmentGrossanlassInquiry || $inquiry->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Anfrage nicht gefunden');
        }
        if ($inquiry->getEmail() === '') {
            throw new \InvalidArgumentException('E-Mail fehlt für ' . $inquiry->getName());
        }
        $account = $this->requireAccount($department);
        $token = $this->gmail->accessToken($account);
        $this->entityManager->flush();
        $merged = $this->merge->preview($department, $inquiry, $kind);
        $labelIds = $this->ensureLabelIds(
            $account,
            $token,
            $this->merge->gmailLabelNames($department, $inquiry, GrossanlassGmailRouting::STATUS_REPLIED),
        );
        $inReplyTo = null;
        if ($inquiry->getGmailThreadId()) {
            try {
                $thread = $this->gmail->listThreadMessages($token, $inquiry->getGmailThreadId());
                for ($i = count($thread) - 1; $i >= 0; --$i) {
                    if (($thread[$i]['messageIdHeader'] ?? '') !== '') {
                        $inReplyTo = $thread[$i]['messageIdHeader'];
                        break;
                    }
                }
            } catch (\Throwable) {
                $inReplyTo = null;
            }
        }
        $draft = $this->gmail->createDraft(
            $token,
            $inquiry->getEmail(),
            $merged['subject'],
            $merged['body'],
            $inquiry->getId(),
            $labelIds,
            $inquiry->getGmailThreadId(),
            $inReplyTo,
        );
        $inquiry->setGmailDraftId($draft['draftId'] !== '' ? $draft['draftId'] : $inquiry->getGmailDraftId());
        if ($draft['threadId'] !== '') {
            $inquiry->setGmailThreadId($draft['threadId']);
        }
        $this->applyStatusForReplyKind($department, $user, $inquiry, $kind);
        $inquiry->appendThread([
            'who' => 'ok',
            'text' => 'Antwort-Entwurf in Gmail: ' . $kind,
        ]);
        $this->entityManager->flush();

        return $this->serializeInquiry($inquiry);
    }

    public function requireAccount(Department $department): DepartmentGrossanlassGmailAccount
    {
        $account = $this->findAccount($department);
        if (!$account instanceof DepartmentGrossanlassGmailAccount) {
            throw new \RuntimeException('Gmail ist nicht verbunden');
        }

        return $account;
    }

    /**
     * @param list<DepartmentGrossanlassInquiry> $inquiries
     * @param array<string, true> $seen
     * @param array<string, mixed> $message
     */
    private function ingestMessage(
        Department $department,
        DepartmentGrossanlassGmailAccount $account,
        string $token,
        array $message,
        ?DepartmentGrossanlassInquiry $inquiry,
        array &$seen,
        User $user,
    ): string {
        unset($user);
        $id = (string) ($message['id'] ?? '');
        if ($id === '' || isset($seen[$id])) {
            return 'skip';
        }
        $labels = [];
        foreach ($message['labelIds'] ?? [] as $label) {
            $labels[] = strtoupper((string) $label);
        }
        if (in_array('DRAFT', $labels, true)) {
            $seen[$id] = true;

            return 'skip';
        }
        $from = (string) ($message['from'] ?? '');
        $subject = (string) ($message['subject'] ?? '');
        $headers = is_array($message['headers'] ?? null) ? $message['headers'] : [];
        if (GrossanlassGmailInbound::isFromAddress($from, $account->getEmail())) {
            $seen[$id] = true;

            return 'skip';
        }
        if (GrossanlassGmailInbound::isIgnorable($headers, $from, $subject)) {
            $seen[$id] = true;

            return 'ignored';
        }
        if ($inquiry instanceof DepartmentGrossanlassInquiry) {
            $this->attachFirmMessage($department, $account, $token, $inquiry, $message, $seen);

            return 'attached';
        }
        $this->storeUnmatched($department, $message);
        $seen[$id] = true;

        return 'unmatched';
    }

    /**
     * @param list<DepartmentGrossanlassInquiry> $inquiries
     * @param array<string, mixed> $message
     */
    private function resolveInquiry(Department $department, array $inquiries, array $message): ?DepartmentGrossanlassInquiry
    {
        $threadId = (string) ($message['threadId'] ?? '');
        if ($threadId !== '') {
            foreach ($inquiries as $inquiry) {
                if ($inquiry->getGmailThreadId() === $threadId) {
                    return $inquiry;
                }
            }
        }
        $headers = is_array($message['headers'] ?? null) ? $message['headers'] : [];
        $headerId = strtolower(trim((string) ($headers['x-ematchef-anfrage'] ?? '')));
        $ids = GrossanlassGmailInbound::findInquiryIds(
            $headerId,
            (string) ($message['subject'] ?? ''),
            (string) ($message['body'] ?? ''),
            (string) ($message['snippet'] ?? ''),
        );
        if ($headerId !== '' && !in_array($headerId, $ids, true) && GrossanlassIdGenerator::matches($headerId, GrossanlassIdGenerator::INQUIRY)) {
            array_unshift($ids, $headerId);
        }
        foreach ($ids as $inquiryId) {
            foreach ($inquiries as $inquiry) {
                if (strtolower($inquiry->getId()) === $inquiryId) {
                    return $inquiry;
                }
            }
        }
        $fromEmail = GrossanlassGmailInbound::parseFrom((string) ($message['from'] ?? ''))['email'];
        if ($fromEmail === '') {
            return null;
        }
        $hits = [];
        foreach ($inquiries as $inquiry) {
            if (strtolower($inquiry->getEmail()) === $fromEmail) {
                $hits[] = $inquiry;
            }
        }
        if (count($hits) === 1) {
            return $hits[0];
        }
        unset($department);

        return null;
    }

    /**
     * @param array<string, mixed> $message
     * @param array<string, true> $seen
     */
    private function attachFirmMessage(
        Department $department,
        DepartmentGrossanlassGmailAccount $account,
        string $token,
        DepartmentGrossanlassInquiry $inquiry,
        array $message,
        array &$seen,
    ): void {
        $id = (string) ($message['id'] ?? '');
        if ($id !== '') {
            $seen[$id] = true;
        }
        foreach ($inquiry->getThread() as $line) {
            if (($line['gmail_message_id'] ?? '') === $id && $id !== '') {
                return;
            }
        }
        $threadId = (string) ($message['threadId'] ?? '');
        if ($threadId !== '' && !$inquiry->getGmailThreadId()) {
            $inquiry->setGmailThreadId($threadId);
        }
        $at = $this->internalDateToAtom((string) ($message['internalDate'] ?? ''));
        $from = GrossanlassGmailInbound::parseFrom((string) ($message['from'] ?? ''));
        $text = trim((string) ($message['body'] ?? ''));
        if ($text === '') {
            $text = trim((string) ($message['snippet'] ?? ''));
        }
        $inquiry->appendThread([
            'who' => 'firm',
            'text' => $text !== '' ? $text : '(kein Text)',
            'at' => $at,
            'from' => $from['email'],
            'subject' => (string) ($message['subject'] ?? ''),
            'gmail_message_id' => $id,
        ]);
        if (in_array($inquiry->getStatus(), [
            DepartmentGrossanlassInquiry::STATUS_GESENDET,
            DepartmentGrossanlassInquiry::STATUS_ENTWURF,
            DepartmentGrossanlassInquiry::STATUS_VORSCHLAG,
        ], true)) {
            $inquiry->setStatus(DepartmentGrossanlassInquiry::STATUS_ANTWORT);
        }
        if ($threadId !== '') {
            $this->applyRepliedLabels($department, $account, $token, $inquiry, $threadId);
        }
    }

    /**
     * @param array<string, mixed> $message
     */
    private function storeUnmatched(Department $department, array $message): void
    {
        $id = (string) ($message['id'] ?? '');
        if ($id === '') {
            return;
        }
        $existing = $this->entityManager->getRepository(DepartmentGrossanlassGmailUnmatched::class)
            ->findOneBy(['departmentId' => $department->getId(), 'gmailMessageId' => $id]);
        if ($existing instanceof DepartmentGrossanlassGmailUnmatched) {
            return;
        }
        $from = GrossanlassGmailInbound::parseFrom((string) ($message['from'] ?? ''));
        $row = new DepartmentGrossanlassGmailUnmatched();
        $row->setId(GrossanlassIdGenerator::unique(
            $this->entityManager,
            GrossanlassIdGenerator::GMAIL_UNMATCHED,
            DepartmentGrossanlassGmailUnmatched::class,
        ));
        $row->setDepartment($department);
        $row->setGmailMessageId($id);
        $row->setGmailThreadId((string) ($message['threadId'] ?? ''));
        $row->setFromEmail($from['email']);
        $row->setFromName($from['name']);
        $row->setSubject(mb_substr((string) ($message['subject'] ?? ''), 0, 255));
        $body = trim((string) ($message['body'] ?? ''));
        if ($body === '') {
            $body = (string) ($message['snippet'] ?? '');
        }
        $row->setBody($body);
        $row->setReceivedAt(new \DateTime($this->internalDateToAtom((string) ($message['internalDate'] ?? ''))));
        $this->entityManager->persist($row);
    }

    /**
     * @param list<object> $inquiries
     * @return array<string, true>
     */
    private function knownMessageIds(Department $department, array $inquiries): array
    {
        $seen = [];
        foreach ($inquiries as $inquiry) {
            if (!$inquiry instanceof DepartmentGrossanlassInquiry) {
                continue;
            }
            if ($inquiry->getGmailMessageId()) {
                $seen[$inquiry->getGmailMessageId()] = true;
            }
            foreach ($inquiry->getThread() as $line) {
                $mid = (string) ($line['gmail_message_id'] ?? '');
                if ($mid !== '') {
                    $seen[$mid] = true;
                }
            }
        }
        $unmatched = $this->entityManager->getRepository(DepartmentGrossanlassGmailUnmatched::class)
            ->findBy(['departmentId' => $department->getId()]);
        foreach ($unmatched as $row) {
            if ($row instanceof DepartmentGrossanlassGmailUnmatched) {
                $seen[$row->getGmailMessageId()] = true;
            }
        }

        return $seen;
    }

    /**
     * @param list<array<string, mixed>> $messages
     */
    private function applyMailboxStatus(
        Department $department,
        DepartmentGrossanlassGmailAccount $account,
        string $token,
        DepartmentGrossanlassInquiry $inquiry,
        array $messages,
        bool $draftThere,
    ): bool {
        $flags = GrossanlassGmailInbound::mailboxFlags($messages, $account->getEmail(), $draftThere);
        $next = GrossanlassGmailInbound::statusFromMailbox(
            $inquiry->getStatus(),
            $flags['has_firm_reply'],
            $flags['has_sent'],
            $flags['has_draft'],
        );
        if ($next === null) {
            return false;
        }
        $inquiry->setStatus($next);
        $note = match ($next) {
            DepartmentGrossanlassInquiry::STATUS_GESENDET => 'In Gmail als gesendet erkannt.',
            DepartmentGrossanlassInquiry::STATUS_ANTWORT => 'Firmenantwort in Gmail erkannt.',
            DepartmentGrossanlassInquiry::STATUS_ENTWURF => 'In Gmail liegt noch ein Entwurf (nicht gesendet).',
            default => 'Status aus Gmail übernommen.',
        };
        $inquiry->appendThread(['who' => 'ok', 'text' => $note]);
        $threadId = $inquiry->getGmailThreadId();
        if ($threadId) {
            if ($next === DepartmentGrossanlassInquiry::STATUS_ANTWORT) {
                $this->applyRepliedLabels($department, $account, $token, $inquiry, $threadId);
            } elseif ($next === DepartmentGrossanlassInquiry::STATUS_GESENDET) {
                $this->applyWaitingLabels($department, $account, $token, $threadId);
            }
        }

        return true;
    }

    private function applyWaitingLabels(
        Department $department,
        DepartmentGrossanlassGmailAccount $account,
        string $token,
        string $threadId,
    ): void {
        $routing = $this->merge->getGmailRouting($department);
        $addNames = array_values(array_filter([
            GrossanlassGmailRouting::statusLabelName($routing, $department->getName(), GrossanlassGmailRouting::STATUS_WAITING),
        ]));
        $removeNames = array_values(array_filter([
            GrossanlassGmailRouting::statusLabelName($routing, $department->getName(), GrossanlassGmailRouting::STATUS_REPLIED),
        ]));
        $add = $this->ensureLabelIds($account, $token, $addNames);
        $remove = [];
        $map = $account->getLabelMap();
        foreach ($removeNames as $name) {
            if (isset($map[$name])) {
                $remove[] = $map[$name];
            }
        }
        try {
            $this->gmail->modifyThreadLabels($token, $threadId, $add, $remove);
        } catch (\Throwable) {
        }
    }

    private function applyRepliedLabels(
        Department $department,
        DepartmentGrossanlassGmailAccount $account,
        string $token,
        DepartmentGrossanlassInquiry $inquiry,
        string $threadId,
    ): void {
        $routing = $this->merge->getGmailRouting($department);
        $addNames = array_values(array_filter([
            GrossanlassGmailRouting::statusLabelName($routing, $department->getName(), GrossanlassGmailRouting::STATUS_REPLIED),
        ]));
        $removeNames = array_values(array_filter([
            GrossanlassGmailRouting::statusLabelName($routing, $department->getName(), GrossanlassGmailRouting::STATUS_WAITING),
        ]));
        $add = $this->ensureLabelIds($account, $token, $addNames);
        $remove = [];
        $map = $account->getLabelMap();
        foreach ($removeNames as $name) {
            if (isset($map[$name])) {
                $remove[] = $map[$name];
            }
        }
        try {
            $this->gmail->modifyThreadLabels($token, $threadId, $add, $remove);
        } catch (\Throwable) {
            // Labels sind Spiegel, Zuordnung in der App gilt trotzdem.
        }
        unset($inquiry);
    }

    /**
     * @param list<string> $names
     * @return list<string>
     */
    private function ensureLabelIds(
        DepartmentGrossanlassGmailAccount $account,
        string $token,
        array $names,
    ): array {
        $map = $account->getLabelMap();
        if ($map === []) {
            foreach ($this->gmail->listLabels($token) as $label) {
                $map[$label['name']] = $label['id'];
            }
        }
        $ids = [];
        foreach ($names as $name) {
            if ($name === '') {
                continue;
            }
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

    private function applyStatusForReplyKind(
        Department $department,
        User $user,
        DepartmentGrossanlassInquiry $inquiry,
        string $kind,
    ): void {
        if ($kind === DepartmentGrossanlassMailTemplate::KIND_ZUSAGE_OK
            || $kind === DepartmentGrossanlassMailTemplate::KIND_NEHMEN
        ) {
            $inquiry->setStatus(DepartmentGrossanlassInquiry::STATUS_ZUSAGE);
            $this->commitments->ensureFromInquiry($department, $user, $inquiry->getId());

            return;
        }
        if ($kind === DepartmentGrossanlassMailTemplate::KIND_DANK_ABSAGE) {
            $inquiry->setStatus(DepartmentGrossanlassInquiry::STATUS_ABSAGE);
        }
    }

    private function findUnmatched(Department $department, string $id): DepartmentGrossanlassGmailUnmatched
    {
        $row = $this->entityManager->getRepository(DepartmentGrossanlassGmailUnmatched::class)->find($id);
        if (!$row instanceof DepartmentGrossanlassGmailUnmatched
            || $row->getDepartmentId() !== $department->getId()
            || $row->getDiscardedAt() !== null
        ) {
            throw new \InvalidArgumentException('Nachricht nicht gefunden');
        }

        return $row;
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
            'reference' => $this->merge->displayReference($inquiry->getDepartment(), $inquiry->getId()),
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
            'gmail_open_url' => $this->openUrl($inquiry),
            'gmail_message_id' => $inquiry->getGmailMessageId(),
            'created_at' => $inquiry->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $inquiry->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUnmatched(DepartmentGrossanlassGmailUnmatched $row): array
    {
        return [
            'id' => $row->getId(),
            'gmail_message_id' => $row->getGmailMessageId(),
            'gmail_thread_id' => $row->getGmailThreadId(),
            'from_email' => $row->getFromEmail(),
            'from_name' => $row->getFromName(),
            'subject' => $row->getSubject(),
            'body' => $row->getBody(),
            'received_at' => $row->getReceivedAt()->format(\DateTimeInterface::ATOM),
            'gmail_open_url' => $row->getGmailThreadId() !== ''
                ? 'https://mail.google.com/mail/u/0/#inbox/' . $row->getGmailThreadId()
                : null,
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

    private function internalDateToAtom(string $internalDate): string
    {
        if ($internalDate !== '' && ctype_digit($internalDate)) {
            $seconds = intdiv((int) $internalDate, 1000);

            return (new \DateTime('@' . $seconds))->format(\DateTimeInterface::ATOM);
        }

        return (new \DateTime())->format(\DateTimeInterface::ATOM);
    }

    private function assertManage(Department $department, User $user): void
    {
        $this->access->assertGrossanlassDepartment($department);
        if (!$this->access->canManagePlanung($user, $department)) {
            throw new \RuntimeException('Keine Berechtigung für Gmail');
        }
    }
}
