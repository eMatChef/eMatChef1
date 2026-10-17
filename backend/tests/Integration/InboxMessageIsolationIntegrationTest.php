<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Department;
use App\Entity\InboxMessage;
use App\Entity\User;
use App\Service\InboxMessageService;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Gemeinsame Abfragen der Nachrichtenzentrale (laden, zählen, als gelesen markieren) je Kategorie:
 * Empfänger-Filter und Department-Isolation. Läuft nur mit EMATCHEF_TEST_DB_URL gegen eine isolierte Datenbank.
 */
final class InboxMessageIsolationIntegrationTest extends TestCase
{
    use IsolatedDatabase;

    private const DEPT_A = 'a57aa6184ef1';
    private const DEPT_B = 'a57aa6184ef2';
    private const USER_1 = 'uitest000001';
    private const USER_2 = 'uitest000002';

    private EntityManagerInterface $em;
    private InboxMessageService $inbox;

    protected function setUp(): void
    {
        $this->em = $this->isolatedEntityManager([self::DEPT_A, self::DEPT_B]);
        $this->seedManagers($this->em->getConnection(), self::DEPT_A);
        $this->inbox = new InboxMessageService($this->em);
    }

    protected function tearDown(): void
    {
        $this->releaseIsolatedDatabase($this->em ?? null);
    }

    public function testUserMessageIsScopedToDepartmentAndRecipient(): void
    {
        $sender = $this->em->find(User::class, self::USER_1);
        $recipient = $this->em->find(User::class, self::USER_2);
        $deptA = $this->em->find(Department::class, self::DEPT_A);
        $this->assertInstanceOf(User::class, $sender);
        $this->assertInstanceOf(User::class, $recipient);
        $this->assertInstanceOf(Department::class, $deptA);

        $sent = $this->inbox->sendUserMessage($deptA, $sender, $recipient, 'Betreff', 'Text');

        $this->assertCount(1, $this->inbox->listUserInbox(self::DEPT_A, self::USER_2));
        $this->assertSame(1, $this->inbox->countUnreadUserMessages(self::DEPT_A, self::USER_2));
        $this->assertSame([], $this->inbox->listUserInbox(self::DEPT_B, self::USER_2), 'anderes Department sieht nichts');
        $this->assertSame([], $this->inbox->listUserInbox(self::DEPT_A, self::USER_1), 'Absender hat die Nachricht nicht im Posteingang');
        $this->assertCount(1, $this->inbox->listUserSent(self::DEPT_A, self::USER_1));

        $this->assertFalse($this->inbox->markUserMessageRead(self::DEPT_B, self::USER_2, $sent['id']));
        $this->assertFalse($this->inbox->markUserMessageRead(self::DEPT_A, self::USER_1, $sent['id']));
        $this->assertSame(1, $this->inbox->countUnreadUserMessages(self::DEPT_A, self::USER_2));

        $this->assertTrue($this->inbox->markUserMessageRead(self::DEPT_A, self::USER_2, $sent['id']));
        $this->assertSame(0, $this->inbox->countUnreadUserMessages(self::DEPT_A, self::USER_2));
        $this->assertCount(0, $this->inbox->listUserInbox(self::DEPT_A, self::USER_2, 'unread'));
        $read = $this->inbox->listUserInbox(self::DEPT_A, self::USER_2, 'read');
        $this->assertCount(1, $read);
        $this->assertTrue($read[0]['read']);
    }

    public function testMaterialManagerMessagesAreDepartmentWide(): void
    {
        $row = $this->persist(self::DEPT_A, InboxMessage::CATEGORY_ACTIVITY_MW, 'activity_submitted', InboxMessage::RECIPIENT_DEPARTMENT_MW, null);
        // Gleiche Kategorie, aber persönlich adressiert: gehört nicht in die MW-Liste.
        $this->persist(self::DEPT_A, InboxMessage::CATEGORY_ACTIVITY_MW, 'activity_submitted', InboxMessage::RECIPIENT_USER, self::USER_2);

        $this->assertCount(1, $this->inbox->listActivityMw(self::DEPT_A));
        $this->assertSame(1, $this->inbox->countUnreadActivityMw(self::DEPT_A));
        $this->assertSame([], $this->inbox->listActivityMw(self::DEPT_B));
        $this->assertFalse($this->inbox->markActivityMwRead(self::DEPT_B, $row->getId()));
        $this->assertTrue($this->inbox->markActivityMwRead(self::DEPT_A, $row->getId()));
        $this->assertSame(0, $this->inbox->countUnreadActivityMw(self::DEPT_A));
    }

    public function testActivityUserMessagesBelongToRecipientInDepartment(): void
    {
        $row = $this->persist(self::DEPT_A, InboxMessage::CATEGORY_ACTIVITY_USER, 'activity_approved', InboxMessage::RECIPIENT_USER, self::USER_2);

        $this->assertCount(1, $this->inbox->listActivityUser(self::DEPT_A, self::USER_2));
        $this->assertSame([], $this->inbox->listActivityUser(self::DEPT_A, self::USER_1));
        $this->assertSame([], $this->inbox->listActivityUser(self::DEPT_B, self::USER_2));
        $this->assertFalse($this->inbox->markActivityUserRead(self::DEPT_A, self::USER_1, $row->getId()));
        $this->assertTrue($this->inbox->markActivityUserRead(self::DEPT_A, self::USER_2, $row->getId()));
        $this->assertSame(0, $this->inbox->countUnreadActivityUser(self::DEPT_A, self::USER_2));
    }

    public function testInviteAcceptedIsScopedToInviterAndDepartment(): void
    {
        $row = $this->persist(self::DEPT_A, InboxMessage::CATEGORY_INVITE_ACCEPTED, 'invite_accepted', InboxMessage::RECIPIENT_USER, self::USER_1);

        $this->assertCount(1, $this->inbox->listInviteAcceptedForInviter(self::DEPT_A, self::USER_1));
        $this->assertSame([], $this->inbox->listInviteAcceptedForInviter(self::DEPT_B, self::USER_1));
        $this->assertSame([], $this->inbox->listInviteAcceptedForInviter(self::DEPT_A, self::USER_2));
        $this->assertSame(1, $this->inbox->countUnreadInviteAccepted(self::DEPT_A, self::USER_1));
        $this->assertFalse($this->inbox->markInviteAcceptedRead(self::DEPT_A, self::USER_2, $row->getId()));
        $this->assertTrue($this->inbox->markInviteAcceptedRead(self::DEPT_A, self::USER_1, $row->getId()));
    }

    public function testGrossanlassMessagesAreOnlyVisibleToTheirRecipient(): void
    {
        $round = $this->persist(self::DEPT_A, InboxMessage::CATEGORY_GROSSANLASS_ROUND_OPENED, 'grossanlass_round_opened', InboxMessage::RECIPIENT_USER, self::USER_2, ['round_name' => 'Runde 1']);
        $mw = $this->persist(self::DEPT_A, InboxMessage::CATEGORY_GROSSANLASS_MW_ASSIGNED, 'grossanlass_mw_assigned', InboxMessage::RECIPIENT_USER, self::USER_2);

        $rounds = $this->inbox->listGrossanlassRoundOpenedForUser(self::USER_2);
        $this->assertCount(1, $rounds);
        $this->assertSame('grossanlass_round_opened', $rounds[0]['type']);
        $this->assertSame('Runde 1', $rounds[0]['round_name']);
        $this->assertFalse($rounds[0]['read']);
        $this->assertSame([], $this->inbox->listGrossanlassRoundOpenedForUser(self::USER_1));
        $this->assertCount(1, $this->inbox->listGrossanlassMwAssignedForUser(self::USER_2));
        $this->assertSame([], $this->inbox->listGrossanlassMwAssignedForUser(self::USER_1));

        // Kategorien sind getrennt: die ID einer MW-Ernennung markiert keine Runden-Meldung.
        $this->assertFalse($this->inbox->markGrossanlassRoundOpenedRead(self::USER_2, $mw->getId()));
        $this->assertFalse($this->inbox->markGrossanlassRoundOpenedRead(self::USER_1, $round->getId()));
        $this->assertSame(1, $this->inbox->countUnreadGrossanlassRoundOpened(self::USER_2));
        $this->assertTrue($this->inbox->markGrossanlassRoundOpenedRead(self::USER_2, $round->getId()));
        $this->assertSame(0, $this->inbox->countUnreadGrossanlassRoundOpened(self::USER_2));
        $this->assertSame(1, $this->inbox->countUnreadGrossanlassMwAssigned(self::USER_2));
        $this->assertTrue($this->inbox->markGrossanlassMwAssignedRead(self::USER_2, $mw->getId()));
    }

    /** @param array<string, mixed> $payload */
    private function persist(string $departmentId, string $category, string $type, string $scope, ?string $recipientUserId, array $payload = []): InboxMessage
    {
        $department = $this->em->find(Department::class, $departmentId);
        $this->assertInstanceOf(Department::class, $department);

        $row = new InboxMessage();
        $row->setId(IdGenerator::generateUnique($this->em, InboxMessage::class));
        $row->setDepartment($department);
        $row->setCategory($category);
        $row->setType($type);
        $row->setRecipientScope($scope);
        $row->setRecipientUserId($recipientUserId);
        $row->setPayload($payload);
        $this->em->persist($row);
        $this->em->flush();

        return $row;
    }
}
