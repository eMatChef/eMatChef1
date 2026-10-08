<?php

declare(strict_types=1);

namespace App\Service\Clock;

use App\Entity\Department;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Fachzeit eines Departments (Grossanlass-Planung, später Material/Wartung …).
 *
 * NICHT für Security/Technik: JWT, Sessions, TOTP, OAuth-State, Token-Ablauf, Rate-Limits
 * und Audit-Zeitstempel (created_at/updated_at) bleiben reale Systemzeit.
 *
 * Auflösung pro Department (nie global, nie pro User-Session):
 *  - kein Kontext / normales Department → reale Zeit
 *  - demo_mode=true → reale Zeit + demo_clock_offset_seconds
 *  - kein Demo, aber Kernel ausserhalb von prod → Entwickler-Zeitreise mit demselben Offset
 *    (EMATCHEF_DEV_TOOLS wirkt hier bewusst nicht; in prod wird ein Offset an einem Nicht-Demo-Department ignoriert)
 *
 * Fachspezifische Ausgangspunkte für «Zurücksetzen» liefern {@see ClockOriginResolverInterface}-Services.
 */
final class BusinessClock
{
    public const MODE_REAL = 'real';
    public const MODE_DEMO = 'demo';
    public const MODE_DEV = 'dev';

    /**
     * @param iterable<ClockOriginResolverInterface> $originResolvers
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private KernelInterface $kernel,
        #[AutowireIterator(ClockOriginResolverInterface::TAG)]
        private iterable $originResolvers = [],
    ) {}

    /** Reale Systemzeit (explizit, z. B. für Audit-Felder in Business-Services). */
    public function realNow(): \DateTime
    {
        return new \DateTime();
    }

    /**
     * @param Department|string|null $context Department oder Department-ID
     */
    public function now(Department|string|null $context = null): \DateTime
    {
        $now = $this->realNow();
        $offset = $this->offsetSeconds($context);
        if ($offset !== 0) {
            $now->modify(sprintf('%+d seconds', $offset));
        }

        return $now;
    }

    /** Mitternacht des Fachtags (Ersatz für new \DateTime('today')). */
    public function today(Department|string|null $context = null): \DateTime
    {
        return $this->now($context)->setTime(0, 0, 0);
    }

    public function offsetSeconds(Department|string|null $context): int
    {
        $department = $this->resolve($context);
        if ($department === null || $this->mode($department) === self::MODE_REAL) {
            return 0;
        }

        return $department->getDemoClockOffsetSeconds() ?? 0;
    }

    public function mode(Department $department): string
    {
        if ($department->isDemoMode()) {
            return self::MODE_DEMO;
        }

        return $this->isDevKernel() ? self::MODE_DEV : self::MODE_REAL;
    }

    /** Ob für dieses Department Zeitreise vorgesehen ist (ohne User-Prüfung). Normales prod-Department → false. */
    public function supportsTravel(Department $department): bool
    {
        return $this->mode($department) !== self::MODE_REAL;
    }

    /** Offset so setzen, dass now() ab jetzt bei $target weiterläuft. */
    public function travelTo(Department $department, \DateTimeInterface $target): void
    {
        $this->assertTravel($department);
        $department->setDemoClockOffsetSeconds($target->getTimestamp() - $this->realNow()->getTimestamp());
    }

    /** Demo-Ausgangspunkt des Departments; ohne Resolver (oder nicht Demo) reale Zeit. */
    public function originFor(Department $department): \DateTimeInterface
    {
        if ($department->isDemoMode()) {
            foreach ($this->originResolvers as $resolver) {
                if ($resolver->supports($department)) {
                    return $resolver->originFor($department);
                }
            }
        }

        return $this->realNow();
    }

    /** Zurück zum Demo-Ausgangspunkt; ohne definierten Ausgangspunkt (oder im Dev) zurück zur realen Zeit. */
    public function reset(Department $department): void
    {
        $this->assertTravel($department);
        if (!$department->isDemoMode()) {
            $department->setDemoClockOffsetSeconds(null);

            return;
        }
        $this->travelTo($department, $this->originFor($department));
    }

    private function assertTravel(Department $department): void
    {
        if (!$this->supportsTravel($department)) {
            throw new \LogicException('Zeitreise ist für dieses Department nicht erlaubt');
        }
    }

    private function resolve(Department|string|null $context): ?Department
    {
        if ($context instanceof Department) {
            return $context;
        }
        if (is_string($context) && $context !== '') {
            return $this->entityManager->getRepository(Department::class)->find($context);
        }

        return null;
    }

    private function isDevKernel(): bool
    {
        return $this->kernel->getEnvironment() !== 'prod';
    }
}
