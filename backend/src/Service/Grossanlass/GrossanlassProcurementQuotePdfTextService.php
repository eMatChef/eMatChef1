<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Text aus Offerten-PDF extrahieren und Kontaktdaten heuristisch erkennen.
 */
class GrossanlassProcurementQuotePdfTextService
{
    /**
     * @return array{
     *     text: string,
     *     company: string|null,
     *     name: string|null,
     *     email: string|null,
     *     phone: string|null,
     *     street: string|null,
     *     postal_code: string|null,
     *     city: string|null,
     *     amount_chf: float|null
     * }
     */
    public function extractFromUploadedFile(UploadedFile $file): array
    {
        $mime = (string) $file->getMimeType();
        if ($mime !== 'application/pdf' && !str_ends_with(strtolower((string) $file->getClientOriginalName()), '.pdf')) {
            throw new \InvalidArgumentException('Nur PDF-Dateien werden unterstützt');
        }

        $binary = (string) file_get_contents((string) $file->getPathname());
        if ($binary === '') {
            throw new \InvalidArgumentException('PDF ist leer');
        }

        $text = $this->extractText($binary);

        return $this->parseContactHints($text);
    }

    /**
     * @return array{
     *     text: string,
     *     company: string|null,
     *     name: string|null,
     *     email: string|null,
     *     phone: string|null,
     *     street: string|null,
     *     postal_code: string|null,
     *     city: string|null,
     *     amount_chf: float|null
     * }
     */
    public function extractFromBinary(string $binary): array
    {
        return $this->parseContactHints($this->extractText($binary));
    }

    private function extractText(string $binary): string
    {
        $pdftotext = $this->extractWithPdftotext($binary);
        if ($pdftotext !== '') {
            return $pdftotext;
        }

        return $this->extractWithRegex($binary);
    }

    private function extractWithPdftotext(string $binary): string
    {
        $tmpIn = tempnam(sys_get_temp_dir(), 'ga_quote_pdf_');
        if ($tmpIn === false) {
            return '';
        }

        $tmpPdf = $tmpIn . '.pdf';
        $tmpTxt = $tmpIn . '.txt';
        @rename($tmpIn, $tmpPdf);

        try {
            if (file_put_contents($tmpPdf, $binary) === false) {
                return '';
            }

            $cmd = sprintf(
                'pdftotext -layout %s %s 2>/dev/null',
                escapeshellarg($tmpPdf),
                escapeshellarg($tmpTxt),
            );
            exec($cmd, $output, $exitCode);

            if ($exitCode !== 0 || !is_file($tmpTxt)) {
                return '';
            }

            return trim((string) file_get_contents($tmpTxt));
        } finally {
            @unlink($tmpPdf);
            @unlink($tmpTxt);
        }
    }

    private function extractWithRegex(string $binary): string
    {
        $parts = [];

        if (preg_match_all('/\((?:\\\\.|[^\\\\)])*+\)/s', $binary, $matches)) {
            foreach ($matches[0] as $raw) {
                $decoded = $this->decodePdfString(substr($raw, 1, -1));
                $decoded = trim($decoded);
                if ($decoded !== '' && mb_strlen($decoded) >= 2) {
                    $parts[] = $decoded;
                }
            }
        }

        if (preg_match_all('/<[0-9a-fA-F]{2}>(?:<[0-9a-fA-F]{2}>)+/s', $binary, $hexMatches)) {
            foreach ($hexMatches[0] as $hexBlock) {
                $decoded = $this->decodePdfHexString($hexBlock);
                $decoded = trim($decoded);
                if ($decoded !== '' && mb_strlen($decoded) >= 2) {
                    $parts[] = $decoded;
                }
            }
        }

        return trim(implode("\n", $parts));
    }

    private function decodePdfString(string $value): string
    {
        $replacements = [
            '\\n' => "\n",
            '\\r' => "\r",
            '\\t' => "\t",
            '\\b' => "\x08",
            '\\f' => "\x0C",
            '\\(' => '(',
            '\\)' => ')',
            '\\\\' => '\\',
        ];

        return strtr($value, $replacements);
    }

    private function decodePdfHexString(string $hexBlock): string
    {
        $hex = preg_replace('/[^0-9a-fA-F]/', '', $hexBlock) ?? '';
        if ($hex === '' || strlen($hex) % 2 !== 0) {
            return '';
        }

        $binary = hex2bin($hex);
        if ($binary === false) {
            return '';
        }

        return mb_convert_encoding($binary, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252') ?: $binary;
    }

    /**
     * @return array{
     *     text: string,
     *     company: string|null,
     *     name: string|null,
     *     email: string|null,
     *     phone: string|null,
     *     street: string|null,
     *     postal_code: string|null,
     *     city: string|null,
     *     amount_chf: float|null
     * }
     */
    private function parseContactHints(string $text): array
    {
        $normalized = preg_replace("/\r\n?/", "\n", $text) ?? $text;
        $lines = array_values(array_filter(array_map('trim', explode("\n", $normalized)), static fn (string $l): bool => $l !== ''));

        $email = null;
        if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $normalized, $m)) {
            $email = strtolower($m[0]);
        }

        $phone = null;
        if (preg_match('/(?:\+41|0)\s*(?:\d[\s\-]?){8,12}\d/u', $normalized, $m)) {
            $phone = preg_replace('/\s+/', ' ', trim($m[0])) ?? trim($m[0]);
        }

        $postalCode = null;
        $city = null;
        $street = null;
        if (preg_match('/\b(\d{4})\s+([A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\-\s\']+)/u', $normalized, $m)) {
            $postalCode = $m[1];
            $city = trim($m[2]);
        }

        foreach ($lines as $line) {
            if ($street !== null) {
                break;
            }
            if (preg_match('/\b(strasse|str\.|weg|gasse|platz|rue|route|chemin|via)\b/ui', $line)
                || preg_match('/\b\d{1,4}[a-z]?\s*,?\s*\d{4}\b/u', $line)) {
                $street = $line;
            }
        }

        $company = null;
        $name = null;
        foreach (array_slice($lines, 0, 8) as $line) {
            if ($email !== null && str_contains($line, $email)) {
                continue;
            }
            if ($phone !== null && str_contains($line, $phone)) {
                continue;
            }
            if (preg_match('/^(offerte|angebot|quote|rechnung|invoice|total|summe|betrag)/ui', $line)) {
                continue;
            }
            if (preg_match('/\b(ag|gmbh|sa|sarl|sàrl|ltd|inc|co\.|s\.?r\.?l\.?)\b/ui', $line)) {
                $company = $line;
                break;
            }
            if ($company === null && mb_strlen($line) >= 3 && !preg_match('/^\d/', $line)) {
                $company = $line;
            }
        }

        $amountChf = $this->extractAmountChf($normalized);

        return [
            'text' => $normalized,
            'company' => $company,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'street' => $street,
            'postal_code' => $postalCode,
            'city' => $city,
            'amount_chf' => $amountChf,
        ];
    }

    private function extractAmountChf(string $text): ?float
    {
        $patterns = [
            '/(?:total|summe|betrag|amount|gesamt)\s*[:\s]*(?:CHF|Fr\.?|SFr\.?)?\s*([\d\'\s.,]+)/ui',
            '/(?:CHF|Fr\.?|SFr\.?)\s*([\d\'\s.,]+)/ui',
        ];

        $best = null;
        foreach ($patterns as $pattern) {
            if (!preg_match_all($pattern, $text, $matches)) {
                continue;
            }
            foreach ($matches[1] as $raw) {
                $amount = $this->parseSwissAmount((string) $raw);
                if ($amount !== null && ($best === null || $amount > $best)) {
                    $best = $amount;
                }
            }
        }

        return $best;
    }

    private function parseSwissAmount(string $raw): ?float
    {
        $clean = str_replace(["'", ' '], '', trim($raw));
        $clean = str_replace(',', '.', $clean);
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $clean)) {
            return null;
        }

        $value = (float) $clean;

        return $value > 0 ? $value : null;
    }
}
