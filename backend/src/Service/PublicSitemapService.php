<?php

namespace App\Service;

use App\Repository\SitePageRepository;

/**
 * Öffentliche Sitemap für die Marketing-Domain (ematchef.ch).
 * Blog-Slugs analog zu frontend/src/utils/publicBlog.ts.
 */
class PublicSitemapService
{
    public function __construct(
        private SitePageRepository $sitePageRepository,
        private SitePageContentDefaults $defaults,
    ) {
    }

    /**
     * @return list<array{loc: string, lastmod: ?string, changefreq: string, priority: string}>
     */
    public function collectUrls(string $siteOrigin): array
    {
        $base = rtrim($siteOrigin, '/');
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');

        $urls = [
            ['loc' => "{$base}/", 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '1.0'],
            ['loc' => "{$base}/faq", 'lastmod' => $now, 'changefreq' => 'monthly', 'priority' => '0.8'],
            ['loc' => "{$base}/blog", 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.8'],
            ['loc' => "{$base}/tos", 'lastmod' => $now, 'changefreq' => 'yearly', 'priority' => '0.3'],
            ['loc' => "{$base}/impressum", 'lastmod' => $now, 'changefreq' => 'yearly', 'priority' => '0.3'],
        ];

        $blogLastmod = $now;
        foreach ($this->publishedBlogPosts() as $post) {
            $slug = $post['slug'];
            $lastmod = $post['lastmod'] ?: $now;
            if ($post['lastmod'] && $post['lastmod'] > $blogLastmod) {
                $blogLastmod = $post['lastmod'];
            }
            $urls[] = [
                'loc' => "{$base}/blog/{$slug}",
                'lastmod' => $lastmod,
                'changefreq' => 'monthly',
                'priority' => '0.6',
            ];
        }

        // Blog-Index lastmod an neuesten Beitrag anpassen
        foreach ($urls as $i => $entry) {
            if ($entry['loc'] === "{$base}/blog") {
                $urls[$i]['lastmod'] = $blogLastmod;
                break;
            }
        }

        return $urls;
    }

    public function toXml(string $siteOrigin): string
    {
        $urls = $this->collectUrls($siteOrigin);
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];
        foreach ($urls as $u) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>'.htmlspecialchars($u['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc>';
            if (!empty($u['lastmod'])) {
                $lines[] = '    <lastmod>'.htmlspecialchars($u['lastmod'], ENT_XML1 | ENT_QUOTES, 'UTF-8').'</lastmod>';
            }
            $lines[] = '    <changefreq>'.htmlspecialchars($u['changefreq'], ENT_XML1 | ENT_QUOTES, 'UTF-8').'</changefreq>';
            $lines[] = '    <priority>'.htmlspecialchars($u['priority'], ENT_XML1 | ENT_QUOTES, 'UTF-8').'</priority>';
            $lines[] = '  </url>';
        }
        $lines[] = '</urlset>';

        return implode("\n", $lines)."\n";
    }

    /**
     * @return list<array{slug: string, lastmod: ?string}>
     */
    private function publishedBlogPosts(): array
    {
        $content = $this->blogContent();
        $posts = $content['posts'] ?? [];
        if (!\is_array($posts)) {
            return [];
        }

        $taken = [];
        $normalized = [];
        foreach ($posts as $i => $raw) {
            if (!\is_array($raw)) {
                continue;
            }
            $status = ($raw['status'] ?? 'published') === 'draft' ? 'draft' : 'published';
            if ($status !== 'published') {
                continue;
            }
            $title = trim((string) ($raw['title'] ?? ''));
            if ($title === '') {
                $title = 'Beitrag';
            }
            $createdAt = (string) ($raw['createdAt'] ?? '');
            $id = \is_string($raw['id'] ?? null) && $raw['id'] !== '' ? $raw['id'] : "legacy-{$i}";
            $normalized[] = [
                'id' => $id,
                'title' => $title,
                'createdAt' => $createdAt,
            ];
        }

        usort($normalized, static function (array $a, array $b): int {
            return strcmp($b['createdAt'], $a['createdAt']);
        });

        $result = [];
        foreach ($normalized as $p) {
            $base = $this->datePrefix($p['createdAt']).'-'.$this->slugify($p['title']);
            if ($base === '' || $base === '-') {
                $base = $p['id'];
            }
            $slug = $base;
            $n = 2;
            while (isset($taken[$slug])) {
                $slug = "{$base}-{$n}";
                ++$n;
            }
            $taken[$slug] = true;
            $result[] = [
                'slug' => $slug,
                'lastmod' => $this->isoToDate($p['createdAt']),
            ];
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function blogContent(): array
    {
        $entity = $this->sitePageRepository->findOneBySlug('blog');
        $content = $entity !== null ? $entity->getContent() : $this->defaults->forSlug('blog');
        $locales = $content['locales'] ?? null;
        if (\is_array($locales)) {
            foreach (['de', 'en', 'fr'] as $loc) {
                $entry = $locales[$loc] ?? null;
                if (\is_array($entry) && isset($entry['posts']) && \is_array($entry['posts']) && $entry['posts'] !== []) {
                    return $entry;
                }
            }
        }

        return $content;
    }

    private function slugify(string $raw): string
    {
        $s = mb_strtolower($raw, 'UTF-8');
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if (\is_string($transliterated) && $transliterated !== '') {
            $s = $transliterated;
        }
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        $s = trim($s, '-');
        $s = preg_replace('/-+/', '-', $s) ?? '';

        return $s;
    }

    private function datePrefix(string $iso): string
    {
        if ($iso === '') {
            return '';
        }
        try {
            $d = new \DateTimeImmutable($iso);

            return $d->format('Y-m');
        } catch (\Exception) {
            return '';
        }
    }

    private function isoToDate(string $iso): ?string
    {
        if ($iso === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable($iso))->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }
}
