<?php

namespace App\Config;

final class LanguageConfig
{
    /** @var list<string> */
    private array $supportedLanguages;
    private string $defaultLanguage;

    /**
     * @param list<string> $supportedLanguages
     */
    public function __construct(array $supportedLanguages, string $defaultLanguage)
    {
        $normalized = [];
        foreach ($supportedLanguages as $language) {
            $value = strtolower(trim((string) $language));
            if ($value === '') {
                continue;
            }
            if (!in_array($value, $normalized, true)) {
                $normalized[] = $value;
            }
        }
        $this->supportedLanguages = count($normalized) > 0 ? $normalized : ['de', 'en', 'fr', 'it'];
        $this->defaultLanguage = $this->normalize($defaultLanguage);
    }

    /**
     * @return list<string>
     */
    public function getSupportedLanguages(): array
    {
        return $this->supportedLanguages;
    }

    public function getDefaultLanguage(): string
    {
        return $this->defaultLanguage;
    }

    public function isSupported(string $language): bool
    {
        return in_array($this->normalize($language), $this->supportedLanguages, true);
    }

    public function normalize(string $language): string
    {
        $value = strtolower(trim($language));
        if ($value === '') {
            return $this->defaultLanguage;
        }
        return $value;
    }

    /**
     * @param list<string>|null $languages
     * @return list<string>|null
     */
    public function normalizeAllowedLanguages(?array $languages): ?array
    {
        if (!is_array($languages) || count($languages) === 0) {
            return null;
        }
        $normalized = [];
        foreach ($languages as $language) {
            $value = $this->normalize((string) $language);
            if (!in_array($value, $this->supportedLanguages, true)) {
                continue;
            }
            if (!in_array($value, $normalized, true)) {
                $normalized[] = $value;
            }
        }
        return count($normalized) > 0 ? $normalized : null;
    }
}
