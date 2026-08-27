<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Service\Grossanlass\GrossanlassGmailInbound;
use PHPUnit\Framework\TestCase;

class GrossanlassGmailInboundTest extends TestCase
{
    public function testFindsInquiryIdInFooterAndHeader(): void
    {
        self::assertSame(
            ['iqabcdef1234'],
            GrossanlassGmailInbound::findInquiryIds(
                'X-eMatChef-Anfrage: iqabcdef1234',
                "Danke\n\nReferenz iqabcdef1234\n",
            ),
        );
    }

    public function testIgnoresOutOfOfficeAndBounce(): void
    {
        self::assertTrue(GrossanlassGmailInbound::isIgnorable(
            ['auto-submitted' => 'auto-replied'],
            'user@firma.ch',
            'Abwesenheitsnotiz',
        ));
        self::assertTrue(GrossanlassGmailInbound::isIgnorable(
            [],
            'MAILER-DAEMON@googlemail.com',
            'Delivery Status Notification (Failure)',
        ));
        self::assertFalse(GrossanlassGmailInbound::isIgnorable(
            ['auto-submitted' => 'no'],
            'einkauf@firma.ch',
            'Re: PFF 2027 – Anfrage',
        ));
    }

    public function testParseFromAndBodyFromPayload(): void
    {
        self::assertSame(
            ['email' => 'a@b.ch', 'name' => 'Anna'],
            GrossanlassGmailInbound::parseFrom('Anna <a@b.ch>'),
        );
        $payload = [
            'mimeType' => 'text/plain',
            'headers' => [['name' => 'From', 'value' => 'x@y.ch']],
            'body' => ['data' => rtrim(strtr(base64_encode('Hallo Firma'), '+/', '-_'), '=')],
        ];
        self::assertSame(['from' => 'x@y.ch'], GrossanlassGmailInbound::headerMap($payload));
        self::assertSame('Hallo Firma', GrossanlassGmailInbound::extractBody($payload));
    }

    public function testStatusFollowsDraftThenSentThenReply(): void
    {
        $ok = 'ok@anlass.ch';
        $draftOnly = GrossanlassGmailInbound::mailboxFlags([
            [
                'from' => 'OK <ok@anlass.ch>',
                'subject' => 'Anfrage',
                'headers' => [],
                'labelIds' => ['DRAFT'],
            ],
        ], $ok, true);
        self::assertTrue($draftOnly['has_draft']);
        self::assertFalse($draftOnly['has_sent']);
        self::assertSame('entwurf', GrossanlassGmailInbound::statusFromMailbox('vorschlag', false, false, true));
        self::assertSame('gesendet', GrossanlassGmailInbound::statusFromMailbox('entwurf', false, true, false));
        self::assertSame('antwort', GrossanlassGmailInbound::statusFromMailbox('gesendet', true, true, false));
        self::assertNull(GrossanlassGmailInbound::statusFromMailbox('zusage', true, true, false));
        self::assertSame('entwurf', GrossanlassGmailInbound::statusFromMailbox('gesendet', false, false, true));

        $sent = GrossanlassGmailInbound::mailboxFlags([
            [
                'from' => 'OK <ok@anlass.ch>',
                'subject' => 'Anfrage',
                'headers' => [],
                'labelIds' => ['SENT'],
            ],
        ], $ok, false);
        self::assertTrue($sent['has_sent']);
        self::assertFalse($sent['has_draft']);
    }
}
