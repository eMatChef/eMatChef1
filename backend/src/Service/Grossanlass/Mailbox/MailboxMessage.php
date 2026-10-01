<?php

declare(strict_types=1);

namespace App\Service\Grossanlass\Mailbox;

/**
 * Normalisierte Mail, unabhängig vom Anbieter.
 */
final class MailboxMessage
{
    /**
     * @param array<string, string> $headers
     * @param list<string> $labelIds
     */
    public function __construct(
        public readonly string $id,
        public readonly string $threadId,
        public readonly string $from,
        public readonly string $subject,
        public readonly string $body,
        public readonly string $snippet,
        public readonly string $messageIdHeader,
        public readonly string $internalDate,
        public readonly array $headers,
        public readonly array $labelIds,
        public readonly bool $isDraft,
        public readonly bool $isSent,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromGmail(array $row): self
    {
        $labels = [];
        foreach ($row['labelIds'] ?? [] as $label) {
            $id = strtoupper((string) $label);
            if ($id !== '') {
                $labels[] = $id;
            }
        }
        $headers = is_array($row['headers'] ?? null) ? $row['headers'] : [];
        $cleanHeaders = [];
        foreach ($headers as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $cleanHeaders[$key] = $value;
            }
        }

        return new self(
            (string) ($row['id'] ?? ''),
            (string) ($row['threadId'] ?? ''),
            (string) ($row['from'] ?? ''),
            (string) ($row['subject'] ?? ''),
            (string) ($row['body'] ?? ''),
            (string) ($row['snippet'] ?? ''),
            (string) ($row['messageIdHeader'] ?? ''),
            (string) ($row['internalDate'] ?? ''),
            $cleanHeaders,
            $labels,
            in_array('DRAFT', $labels, true),
            in_array('SENT', $labels, true),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'threadId' => $this->threadId,
            'from' => $this->from,
            'subject' => $this->subject,
            'body' => $this->body,
            'snippet' => $this->snippet,
            'messageIdHeader' => $this->messageIdHeader,
            'internalDate' => $this->internalDate,
            'headers' => $this->headers,
            'labelIds' => $this->labelIds,
            'isDraft' => $this->isDraft,
            'isSent' => $this->isSent,
        ];
    }
}
