<?php

namespace App\Util;

/**
 * ID Generator für 12-stellige hexadezimale IDs
 */
class IdGenerator
{
    public const PUBLIC_CODE_MAX_ATTEMPTS = 20;

    /**
     * Generiert eine 12-stellige hexadezimale ID
     * Kollisionswahrscheinlichkeit: 1 zu 2^48 ≈ 281 Billionen
     */
    public static function generate(): string
    {
        // Generiere 6 Bytes = 12 hex Zeichen
        $bytes = random_bytes(6);
        return bin2hex($bytes);
    }

    /**
     * Generiert eine eindeutige ID mit Datenbankprüfung
     * Falls Kollision, wird eine neue ID generiert
     */
    public static function generateUnique(\Doctrine\ORM\EntityManagerInterface $em, string $entityClass, string $field = 'id'): string
    {
        $maxAttempts = 10;
        $attempt = 0;
        
        do {
            $id = self::generate();
            $attempt++;
            
            // Prüfe ob ID bereits existiert
            $existing = $em->getRepository($entityClass)->findOneBy([$field => $id]);
            if (!$existing) {
                return $id;
            }
            
        } while ($attempt < $maxAttempts);
        
        throw new \RuntimeException('Konnte nach ' . $maxAttempts . ' Versuchen keine eindeutige ID generieren');
    }

    /**
     * Validiert eine ID auf korrektes Format
     */
    public static function isValid(string $id): bool
    {
        return preg_match('/^[0-9a-f]{12}$/i', $id) === 1;
    }

    /**
     * Generiert eine ID mit Präfix (für verschiedene Entitätstypen)
     * Achtung: Ergebnis ist prefix.length + 12 Zeichen lang!
     */
    public static function generateWithPrefix(string $prefix): string
    {
        $id = self::generate();
        return strtoupper($prefix) . $id;
    }

    /**
     * Generiert eine 12-stellige ID mit Präfix
     * Format: prefix + hex, insgesamt immer 12 Zeichen
     * z.B. "grp" + 9 hex = "grpa3f1b9c2d" (12 Zeichen)
     */
    public static function generate12WithPrefix(string $prefix): string
    {
        $prefixLen = strlen($prefix);
        $hexLen = 12 - $prefixLen;
        if ($hexLen < 4) {
            throw new \InvalidArgumentException('Prefix zu lang für 12-stellige ID (max ' . (12 - 4) . ' Zeichen)');
        }
        $bytes = random_bytes((int) ceil($hexLen / 2));
        $hex = substr(bin2hex($bytes), 0, $hexLen);
        return $prefix . $hex;
    }

    /**
     * Generiert eine eindeutige 12-stellige ID mit Präfix und DB-Prüfung
     */
    public static function generate12UniqueWithPrefix(\Doctrine\ORM\EntityManagerInterface $em, string $entityClass, string $prefix, string $field = 'id'): string
    {
        $maxAttempts = 10;
        $attempt = 0;
        
        do {
            $id = self::generate12WithPrefix($prefix);
            $attempt++;
            
            $existing = $em->getRepository($entityClass)->findOneBy([$field => $id]);
            if (!$existing) {
                return $id;
            }
        } while ($attempt < $maxAttempts);
        
        throw new \RuntimeException('Konnte nach ' . $maxAttempts . ' Versuchen keine eindeutige ID generieren');
    }

    /**
     * Generiert eine 13-stellige ID mit Jahr (für Transaktionen)
     * Format: Prefix(2) + YYYY(4) + HEX7(7)
     * Beispiel: or2025a3f1b9c, ln2025b4c2d3e, ba2026f1a2b3c
     * 
     * @param string $prefix 2-stelliger Prefix (or, oi, oh, ln, li, lh, hi, df, ba, lg)
     * @param string|null $year Optional: Jahr für die ID (z.B. aus Einkaufsdatum). Default: aktuelles Jahr.
     * @return string 13-stellige ID
     */
    public static function generate13(string $prefix, ?string $year = null): string
    {
        if (strlen($prefix) !== 2) {
            throw new \InvalidArgumentException('Prefix muss genau 2 Zeichen lang sein');
        }

        $year = $year ?? date('Y');
        $randomHex = bin2hex(random_bytes(4)); // 8 chars
        $randomHex = substr($randomHex, 0, 7); // take first 7
        
        return $prefix . $year . $randomHex;
    }

    /**
     * Generiert eine eindeutige 13-stellige ID mit Datenbankprüfung
     * KRITISCH für 900+ Abteilungen um Kollisionen zu vermeiden!
     * 
     * @param \Doctrine\ORM\EntityManagerInterface $em Entity Manager
     * @param string $entityClass Entity-Klasse (z.B. 'App\Entity\MaterialBatch')
     * @param string $prefix 2-stelliger Prefix (z.B. 'ba', 'df', 'hi')
     * @param string $field Feldname für ID-Prüfung (default: 'id')
     * @return string Eindeutige 13-stellige ID
     * @throws \RuntimeException wenn nach 50 Versuchen keine eindeutige ID generiert werden konnte
     */
    public static function generate13Unique(\Doctrine\ORM\EntityManagerInterface $em, string $entityClass, string $prefix, string $field = 'id'): string
    {
        $maxAttempts = 50; // Bei 16 Mio Möglichkeiten sollten 50 Versuche reichen
        $attempt = 0;
        
        do {
            $id = self::generate13($prefix);
            $attempt++;
            
            // Prüfe ob ID bereits existiert
            $existing = $em->getRepository($entityClass)->findOneBy([$field => $id]);
            if (!$existing) {
                return $id;
            }
            
            // Log bei Kollision (sehr selten, aber wichtig zu wissen)
            error_log(sprintf(
                'ID-Kollision erkannt! Entity: %s, Prefix: %s, Versuch: %d/%d, ID: %s',
                $entityClass,
                $prefix,
                $attempt,
                $maxAttempts,
                $id
            ));
            
        } while ($attempt < $maxAttempts);
        
        throw new \RuntimeException(sprintf(
            'Konnte nach %d Versuchen keine eindeutige 13-stellige ID für %s (Prefix: %s) generieren. Möglicherweise sind alle IDs für dieses Jahr erschöpft!',
            $maxAttempts,
            $entityClass,
            $prefix
        ));
    }

    /**
     * Wie generate13Unique, aber mit festem Buchungs-/Geschäftsjahr (z. B. Buchungsdatum).
     */
    public static function generate13UniqueForYear(
        \Doctrine\ORM\EntityManagerInterface $em,
        string $entityClass,
        string $prefix,
        string $year,
        string $field = 'id'
    ): string {
        $maxAttempts = 50;
        $attempt = 0;
        do {
            $id = self::generate13($prefix, $year);
            ++$attempt;
            $existing = $em->getRepository($entityClass)->findOneBy([$field => $id]);
            if (!$existing) {
                return $id;
            }
            error_log(sprintf(
                'ID-Kollision (Jahr %s)! Entity: %s, Prefix: %s, Versuch: %d/%d',
                $year,
                $entityClass,
                $prefix,
                $attempt,
                $maxAttempts
            ));
        } while ($attempt < $maxAttempts);

        throw new \RuntimeException(sprintf(
            'Konnte nach %d Versuchen keine eindeutige 13-stellige ID für %s (Prefix: %s, Jahr: %s) generieren.',
            $maxAttempts,
            $entityClass,
            $prefix,
            $year
        ));
    }

    /**
     * Validiert eine 13-stellige ID
     * Format: Prefix(2) + YYYY(4) + HEX7(7)
     */
    public static function isValid13(string $id, ?string $expectedPrefix = null): bool
    {
        // Basis-Format prüfen
        if (!preg_match('/^[a-z]{2}(20[0-9]{2}|21[0-9]{2})[0-9a-f]{7}$/', $id)) {
            return false;
        }

        // Optional: Prefix prüfen
        if ($expectedPrefix !== null) {
            return str_starts_with($id, $expectedPrefix);
        }

        return true;
    }

    /**
     * Gibt den Prefix basierend auf Entity-Klasse zurück
     * 
     * @param string $entityClass Vollständiger Klassenname (z.B. App\Entity\Order)
     * @return array{type: string, prefix: string, length: int} ID-Config
     */
    public static function getEntityIdConfig(string $entityClass): array
    {
        // Transaktions-Entities (13-stellig mit Jahr)
        $transaction13 = [
            'Order' => ['prefix' => 'or', 'length' => 13],
            'OrderItem' => ['prefix' => 'oi', 'length' => 13],
            'OrderHistory' => ['prefix' => 'oh', 'length' => 13],
            'OrderDraftHistory' => ['prefix' => 'od', 'length' => 13],
            'Loan' => ['prefix' => 'ln', 'length' => 13],
            'LoanItem' => ['prefix' => 'li', 'length' => 13],
            'LoanHistory' => ['prefix' => 'lh', 'length' => 13],
            'MaterialItemHistory' => ['prefix' => 'hi', 'length' => 13],
            'MaterialBatchHistory' => ['prefix' => 'hb', 'length' => 13],
            'MaterialDefectHistory' => ['prefix' => 'hd', 'length' => 13],
            'MaterialDefect' => ['prefix' => 'df', 'length' => 13],
            'MaterialBatch' => ['prefix' => 'ba', 'length' => 13],
            'BatchStorageAllocation' => ['prefix' => 'al', 'length' => 13],
            'MaterialStockLedger' => ['prefix' => 'lg', 'length' => 13],
            'CategoryHistory' => ['prefix' => 'ca', 'length' => 13],
            'DepartmentHistory' => ['prefix' => 'dh', 'length' => 13],
            'Event' => ['prefix' => 'ev', 'length' => 13],
            // Activity Workflow
            'ActivityItem' => ['prefix' => 'ai', 'length' => 13],
            'ActivityHistory' => ['prefix' => 'ah', 'length' => 13],
            'ActivityPackItem' => ['prefix' => 'pk', 'length' => 13],
            'ActivityReturnItem' => ['prefix' => 'ri', 'length' => 13],
            'ActivityIssueReport' => ['prefix' => 'ir', 'length' => 13],
            // Zelt-/Combo-Entities
            'MaterialComboComponent' => ['prefix' => 'cc', 'length' => 13],
            'MaterialTentDefect' => ['prefix' => 'td', 'length' => 13],
            'MaterialTentHistory' => ['prefix' => 'th', 'length' => 13],
            'WorkshopTicket' => ['prefix' => 'wt', 'length' => 13],
            'WorkshopTicketHistory' => ['prefix' => 'wh', 'length' => 13],
        ];

        // Entity-Name extrahieren
        $className = substr($entityClass, strrpos($entityClass, '\\') + 1);

        // Prüfen ob Transaktions-Entity
        if (isset($transaction13[$className])) {
            return [
                'type' => 'transaction',
                'prefix' => $transaction13[$className]['prefix'],
                'length' => $transaction13[$className]['length']
            ];
        }

        // Stammdaten mit Prefix (12-stellig, prefix + hex)
        $masterWithPrefix = [
            'Group' => ['prefix' => 'grp'],
        ];

        if (isset($masterWithPrefix[$className])) {
            return [
                'type' => 'master_prefixed',
                'prefix' => $masterWithPrefix[$className]['prefix'],
                'length' => 12
            ];
        }

        // Stammdaten (12-stellig, rein hex)
        return [
            'type' => 'master',
            'prefix' => null,
            'length' => 12
        ];
    }

    /**
     * Generiert ID basierend auf Entity-Typ
     * Erkennt automatisch ob 12-stellig (Stammdaten) oder 13-stellig (Transaktionen)
     * 
     * @param object $entity Die Entity
     * @return string Generierte ID
     */
    public static function generateForEntity(object $entity): string
    {
        $config = self::getEntityIdConfig(get_class($entity));

        if ($config['type'] === 'transaction') {
            return self::generate13($config['prefix']);
        }

        if ($config['type'] === 'master_prefixed') {
            return self::generate12WithPrefix($config['prefix']);
        }

        return self::generate();
    }

    /**
     * Generiert einen URL-sicheren Public-Code mit hoher Entropie.
     * Standard: 10 Bytes = 80 Bit Entropie.
     */
    public static function generatePublicCode(int $bytes = 10): string
    {
        if ($bytes < 10) {
            throw new \InvalidArgumentException('Public-Code benötigt mindestens 10 Bytes (80 Bit) Entropie.');
        }
        $raw = random_bytes($bytes);
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * Generiert einen eindeutigen Public-Code mit DB-Prüfung.
     */
    public static function generateUniquePublicCode(
        \Doctrine\ORM\EntityManagerInterface $em,
        string $entityClass = \App\Entity\PublicCode::class,
        string $field = 'publicCode',
        ?int $maxAttempts = null
    ): string {
        $attemptLimit = max(1, (int) ($maxAttempts ?? self::PUBLIC_CODE_MAX_ATTEMPTS));
        for ($attempt = 0; $attempt < $attemptLimit; $attempt++) {
            $code = self::generatePublicCode();
            $existing = $em->getRepository($entityClass)->findOneBy([$field => $code]);
            if (!$existing) {
                return $code;
            }
        }

        throw new \RuntimeException('Konnte nach ' . $attemptLimit . ' Versuchen keinen eindeutigen Public-Code generieren');
    }
}
