<?php

namespace App\Service\Mail;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Transport: ausschliesslich MAILER_DSN aus der Umgebung (HTTPS-API).
 */
final class MailTransportResolver
{
    private ?TransportInterface $transport = null;

    private ?string $cacheKey = null;

    public function __construct(
        private MailOutboundSettingsStore $store,
        #[Autowire('%env(MAILER_DSN)%')]
        private string $fallbackDsn
    ) {
    }

    public function getTransport(): TransportInterface
    {
        $r = $this->store->resolveMailTransport($this->fallbackDsn);
        $key = $r['cache_key'];
        if ($this->transport !== null && $this->cacheKey === $key) {
            return $this->transport;
        }
        $this->cacheKey = $key;
        $this->transport = Transport::fromDsn($r['dsn']);

        return $this->transport;
    }
}
