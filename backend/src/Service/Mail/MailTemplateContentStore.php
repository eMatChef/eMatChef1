<?php

namespace App\Service\Mail;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

/**
 * Lädt übersetzbare System-Mail-Texte aus config/mail_templates/{locale}.json
 * und optionalen Superadmin-Overrides in var/app/mail_template_overrides.json.
 *
 * Crowdin: source backend/config/mail_templates/de.json → targets en/fr/it (siehe crowdin.yml).
 */
final class MailTemplateContentStore
{
    /** @var list<string> */
    private const MAIL_LOCALES = ['de', 'en', 'fr', 'it'];

    /**
     * Nur Inhalts-Vorlagen (für Superadmin-Editor) — kein _api, _shared, admin, …
     *
     * @var list<string>
     */
    private const TEMPLATE_MESSAGE_KEYS = [
        'auth.verify_email',
        'auth.pending_email_change',
        'auth.password_reset_code',
        'department.invite',
        'public.found_item_contact',
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getMailLocales(): array
    {
        return self::MAIL_LOCALES;
    }

    public function resolveMailLocale(?string $profileLanguage): string
    {
        $n = strtolower(trim((string) $profileLanguage));
        if ($n === '') {
            return 'de';
        }
        if (str_starts_with($n, 'en')) {
            return 'en';
        }
        if (str_starts_with($n, 'fr')) {
            return 'fr';
        }
        if (str_starts_with($n, 'it')) {
            return 'it';
        }

        return 'de';
    }

    public function normalizeLocaleParam(string $locale): string
    {
        $l = strtolower(trim($locale));
        if (!in_array($l, self::MAIL_LOCALES, true)) {
            return 'de';
        }

        return $l;
    }

    public function localeForApiRequest(?Request $request): string
    {
        if ($request === null) {
            return 'de';
        }
        $l = $request->getPreferredLanguage(self::MAIL_LOCALES);
        if (is_string($l) && $l !== '') {
            return $this->normalizeLocaleParam($l);
        }

        return 'de';
    }

    /**
     * API-/System-Strings (verschachtelt in _api, z. B. "mt.unauth", "pfd.message_empty").
     */
    public function getApiString(string $path, string $locale = 'de'): string
    {
        $loc = $this->normalizeLocaleParam($locale);
        $merged = $this->loadMergedLocale($loc);
        $v = $this->readPathFromNode($merged['_api'] ?? null, $path);
        if (is_string($v) && $v !== '') {
            return $v;
        }
        if ($loc !== 'de') {
            return $this->getApiString($path, 'de');
        }

        return $path;
    }

    public function getSharedString(string $key, string $locale = 'de'): string
    {
        $loc = $this->normalizeLocaleParam($locale);
        $merged = $this->loadMergedLocale($loc);
        $s = $merged['_shared'] ?? null;
        if (is_array($s) && array_key_exists($key, $s) && is_string($s[$key]) && $s[$key] !== '') {
            return $s[$key];
        }
        if ($loc !== 'de') {
            return $this->getSharedString($key, 'de');
        }

        return 'eMatChef';
    }

    /**
     * @return mixed|null
     */
    private function readPathFromNode(mixed $node, string $dotted)
    {
        if (!is_string($dotted) || $dotted === '') {
            return null;
        }
        $parts = explode('.', $dotted);
        $cur = $node;
        foreach ($parts as $p) {
            if (!is_array($cur) || !array_key_exists($p, $cur)) {
                return null;
            }
            $cur = $cur[$p];
        }

        return $cur;
    }

    /**
     * Katalog-Einträge für die Superadmin-Übersicht (Titel/Beschreibung/Preview).
     *
     * @return list<array{key: string, title: string, description: string, subject: string, body_preview: string}>
     */
    public function getCatalogForLocale(string $locale): array
    {
        $locale = $this->normalizeLocaleParam($locale);
        $merged = $this->loadMergedLocale($locale);
        $catalog = $merged['_catalog'] ?? [];
        if (!is_array($catalog)) {
            return [];
        }

        $out = [];
        foreach ($catalog as $key => $meta) {
            if (!is_string($key) || $key === '' || $key[0] === '_') {
                continue;
            }
            if (!is_array($meta)) {
                continue;
            }
            $tpl = $merged[$key] ?? [];
            $subject = is_array($tpl) && isset($tpl['subject']) ? (string) $tpl['subject'] : '';
            $out[] = [
                'key' => $key,
                'title' => (string) ($meta['title'] ?? $key),
                'description' => (string) ($meta['description'] ?? ''),
                'subject' => $subject,
                'body_preview' => (string) ($meta['body_preview'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Alle Nachrichten-Blöcke (ohne _catalog) für Editor/Speichern.
     *
     * @return array<string, mixed>
     */
    public function getMessagesForLocale(string $locale): array
    {
        $locale = $this->normalizeLocaleParam($locale);
        $merged = $this->loadMergedLocale($locale);
        $out = [];
        foreach (self::TEMPLATE_MESSAGE_KEYS as $key) {
            if (isset($merged[$key]) && is_array($merged[$key])) {
                $out[$key] = $merged[$key];
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $partialMessages key => partial fields
     */
    public function mergeOverrides(string $locale, array $partialMessages): void
    {
        $locale = $this->normalizeLocaleParam($locale);
        $path = $this->overridesPath();
        $root = $this->readJsonFile($path) ?? [];

        if (!isset($root[$locale]) || !is_array($root[$locale])) {
            $root[$locale] = [];
        }

        foreach ($partialMessages as $key => $patch) {
            if (!is_string($key) || $key === '' || $key[0] === '_' || $key === '_catalog') {
                continue;
            }
            if (!in_array($key, self::TEMPLATE_MESSAGE_KEYS, true)) {
                continue;
            }
            if (!is_array($patch)) {
                continue;
            }
            $existing = isset($root[$locale][$key]) && is_array($root[$locale][$key]) ? $root[$locale][$key] : [];
            $root[$locale][$key] = $this->arrayMergeRecursiveDistinct($existing, $this->sanitizeTemplatePatch($patch));
        }

        $this->writeJsonFile($path, $root);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getTemplate(string $key, string $locale): ?array
    {
        $locale = $this->normalizeLocaleParam($locale);
        $merged = $this->loadMergedLocale($locale);
        if (!isset($merged[$key]) || !is_array($merged[$key])) {
            return null;
        }

        return $merged[$key];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadMergedLocale(string $locale): array
    {
        $defaults = $this->readJsonFile($this->defaultsPath($locale)) ?? [];
        if ($locale !== 'de') {
            $fallback = $this->readJsonFile($this->defaultsPath('de')) ?? [];
            $defaults = $this->arrayMergeRecursiveDistinct($fallback, $defaults);
        }

        $overridesRoot = $this->readJsonFile($this->overridesPath()) ?? [];
        $overrides = (isset($overridesRoot[$locale]) && is_array($overridesRoot[$locale])) ? $overridesRoot[$locale] : [];

        return $this->arrayMergeRecursiveDistinct($defaults, $overrides);
    }

    private function defaultsPath(string $locale): string
    {
        return $this->projectDir . '/config/mail_templates/' . $locale . '.json';
    }

    private function overridesPath(): string
    {
        return $this->projectDir . '/var/app/mail_template_overrides.json';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJsonFile(string $path): ?array
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeJsonFile(string $path, array $data): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        file_put_contents($path, $json . "\n", LOCK_EX);
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     * @return array<string, mixed>
     */
    private function arrayMergeRecursiveDistinct(array $a, array $b): array
    {
        foreach ($b as $key => $value) {
            if (is_int($key)) {
                $a[] = $value;
                continue;
            }
            if (!array_key_exists($key, $a) || !is_array($a[$key]) || !is_array($value)) {
                $a[$key] = $value;
                continue;
            }
            $a[$key] = $this->arrayMergeRecursiveDistinct($a[$key], $value);
        }

        return $a;
    }

    /**
     * @param array<string, mixed> $patch
     * @return array<string, mixed>
     */
    private function sanitizeTemplatePatch(array $patch): array
    {
        $out = [];
        foreach (['subject', 'text_body', 'inviter_name_fallback', 'line_serial', 'sender_name_line', 'sender_email_line', 'sender_value_empty'] as $k) {
            if (!array_key_exists($k, $patch)) {
                continue;
            }
            $out[$k] = $this->sanitizePlain((string) $patch[$k]);
        }
        if (isset($patch['html']) && is_array($patch['html'])) {
            $html = [];
            foreach ($patch['html'] as $hk => $hv) {
                if (!is_string($hk) || preg_match('/^[a-z0-9_]+$/', $hk) !== 1) {
                    continue;
                }
                $html[$hk] = $this->sanitizeRichHtml((string) $hv);
            }
            if (count($html) > 0) {
                $out['html'] = $html;
            }
        }

        return $out;
    }

    private function sanitizePlain(string $value): string
    {
        $value = str_replace(["\0"], '', $value);
        if (mb_strlen($value) > 20000) {
            $value = mb_substr($value, 0, 20000);
        }

        return $value;
    }

    private function sanitizeRichHtml(string $html): string
    {
        $html = str_replace(["\0"], '', $html);
        $html = strip_tags($html, '<p><br><strong><b><em><i><u><a><ul><ol><li><h2><h3><span>');
        $html = preg_replace('#<(script|iframe|object|embed)[^>]*>.*?</\1>#is', '', $html) ?? '';
        $html = preg_replace('#\s(on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+))#i', '', $html) ?? '';
        $html = preg_replace_callback('#<a\b[^>]*>#i', function (array $m): string {
            $tag = $m[0];
            if (preg_match('#\bhref\s*=\s*("|\')([^"\']+)\1#i', $tag, $h)) {
                $url = trim($h[2]);
                if (!preg_match('#^https?://#i', $url)) {
                    return '<a>';
                }
                $safe = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                return '<a href="' . $safe . '">';
            }

            return '<a>';
        }, $html) ?? '';
        if (mb_strlen($html) > 50000) {
            $html = mb_substr($html, 0, 50000);
        }

        return $html;
    }

    /**
     * @param array<string, string|int|float> $vars
     */
    public function interpolate(string $template, array $vars): string
    {
        $replace = [];
        foreach ($vars as $k => $v) {
            if (!is_string($k) || $k === '') {
                continue;
            }
            $replace['{{' . $k . '}}'] = (string) $v;
        }

        return strtr($template, $replace);
    }
}
