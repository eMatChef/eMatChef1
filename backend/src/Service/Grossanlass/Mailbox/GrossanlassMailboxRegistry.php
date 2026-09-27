<?php

declare(strict_types=1);

namespace App\Service\Grossanlass\Mailbox;

use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final class GrossanlassMailboxRegistry
{
    /** @var array<string, GrossanlassMailboxProvider> */
    private array $byId = [];

    /**
     * @param iterable<GrossanlassMailboxProvider> $providers
     */
    public function __construct(
        #[TaggedIterator('app.grossanlass_mailbox')]
        iterable $providers,
    ) {
        foreach ($providers as $provider) {
            $this->byId[$provider->id()] = $provider;
        }
    }

    public function get(string $id): GrossanlassMailboxProvider
    {
        if (!isset($this->byId[$id])) {
            throw new MailboxProviderException('Unbekanntes Postfach: ' . $id);
        }

        return $this->byId[$id];
    }

    /**
     * @return list<array{id: string, label: string, configured: bool, redirect_uri: string, capabilities: array{drafts: bool, inbox: bool, folders: bool}}>
     */
    public function catalog(): array
    {
        $out = [];
        foreach ($this->byId as $provider) {
            $out[] = [
                'id' => $provider->id(),
                'label' => $provider->label(),
                'configured' => $provider->isConfigured(),
                'redirect_uri' => $provider->redirectUri(),
                'capabilities' => $provider->capabilities(),
            ];
        }
        usort($out, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        return $out;
    }
}
