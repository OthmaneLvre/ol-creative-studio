<?php

declare(strict_types=1);

final class EmailFinder
{
    private const MAX_PAGES = 8;

    private const PRIORITY_PATHS = [
        '/',
        '/contact',
        '/contact/',
        '/mentions-legales',
        '/mentions-legales/',
        '/mentions-legales.html',
        '/mentions-legales.php',
        '/a-propos',
        '/a-propos/',
        '/about',
        '/about/',
    ];

    private const GENERIC_PREFIXES = [
        'contact',
        'bonjour',
        'hello',
        'info',
        'infos',
        'accueil',
        'commercial',
        'commerce',
        'direction',
        'secretariat',
        'secretaire',
        'serviceclient',
        'service-client',
        'support',
        'admin',
        'administration',
    ];

    private const BLOCKED_PREFIXES = [
        'noreply',
        'no-reply',
        'donotreply',
        'do-not-reply',
        'test',
        'example',
        'exemple',
    ];

    public function __construct(
        private readonly int $timeout = 10
    ) {
    }

    /**
     * Recherche les adresses e-mail publiques présentes
     * sur le site officiel.
     *
     * Retour :
     * [
     *     [
     *         'email' => 'contact@example.fr',
     *         'type' => 'generic',
     *         'source_url' => 'https://example.fr/contact',
     *     ],
     * ]
     */
    public function find(string $websiteUrl): array
    {
        $baseUrl = $this->normalizeWebsiteUrl($websiteUrl);

        $baseHost = $this->extractHost($baseUrl);

        if ($baseHost === null) {
            throw new InvalidArgumentException(
                'URL du site invalide.'
            );
        }

        $urls = $this->buildPriorityUrls($baseUrl);

        $visited = [];
        $emails = [];

        foreach ($urls as $url) {
            if (count($visited) >= self::MAX_PAGES) {
                break;
            }

            if (isset($visited[$url])) {
                continue;
            }

            $visited[$url] = true;

            try {
                $page = $this->fetchPage($url);
            } catch (Throwable) {
                continue;
            }

            if ($page === null) {
                continue;
            }

            $finalUrl = $page['final_url'];

            if (!$this->isSameHost($finalUrl, $baseHost)) {
                continue;
            }

            foreach (
                $this->extractEmails(
                    $page['body'],
                    $finalUrl
                ) as $email
            ) {
                $key = strtolower($email['email']);

                if (!isset($emails[$key])) {
                    $emails[$key] = $email;
                }
            }

            foreach (
                $this->extractRelevantLinks(
                    $page['body'],
                    $finalUrl,
                    $baseHost
                ) as $linkedUrl
            ) {
                if (
                    count($urls) < self::MAX_PAGES
                    && !in_array($linkedUrl, $urls, true)
                ) {
                    $urls[] = $linkedUrl;
                }
            }
        }

        return array_values($emails);
    }

    private function normalizeWebsiteUrl(
        string $websiteUrl
    ): string {
        $websiteUrl = trim($websiteUrl);

        if ($websiteUrl === '') {
            throw new InvalidArgumentException(
                'Le site du prospect est obligatoire.'
            );
        }

        if (!preg_match(
            '#^https?://#i',
            $websiteUrl
        )) {
            $websiteUrl = 'https://' . $websiteUrl;
        }

        $parts = parse_url($websiteUrl);

        if (
            $parts === false
            || !isset($parts['host'])
        ) {
            throw new InvalidArgumentException(
                'URL du site invalide.'
            );
        }

        $scheme = isset($parts['scheme'])
            ? strtolower((string) $parts['scheme'])
            : 'https';

        $host = strtolower(
            (string) $parts['host']
        );

        return $scheme . '://' . $host;
    }

    private function buildPriorityUrls(
        string $baseUrl
    ): array {
        $urls = [];

        foreach (self::PRIORITY_PATHS as $path) {
            $urls[] = rtrim($baseUrl, '/')
                . $path;
        }

        return array_values(
            array_unique($urls)
        );
    }

    private function fetchPage(
        string $url
    ): ?array {
        $curl = curl_init($url);

        if ($curl === false) {
            throw new RuntimeException(
                'Impossible d’initialiser la requête HTTP.'
            );
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT =>
                'OL-Creative-Studio-Prospection/1.0',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml',
            ],
        ]);

        $body = curl_exec($curl);

        if ($body === false) {
            $error = curl_error($curl);

            curl_close($curl);

            throw new RuntimeException(
                'Erreur HTTP : ' . $error
            );
        }

        $httpCode = (int) curl_getinfo(
            $curl,
            CURLINFO_RESPONSE_CODE
        );

        $contentType = (string) curl_getinfo(
            $curl,
            CURLINFO_CONTENT_TYPE
        );

        $finalUrl = (string) curl_getinfo(
            $curl,
            CURLINFO_EFFECTIVE_URL
        );

        curl_close($curl);

        if ($httpCode < 200 || $httpCode >= 300) {
            return null;
        }

        if (
            $contentType !== ''
            && !str_contains(
                strtolower($contentType),
                'text/html'
            )
        ) {
            return null;
        }

        return [
            'body' => (string) $body,
            'final_url' => $finalUrl !== ''
                ? $finalUrl
                : $url,
        ];
    }

    private function extractEmails(
        string $html,
        string $sourceUrl
    ): array {
        $candidates = [];

        preg_match_all(
            '/mailto:([^"\'>\s?]+)/i',
            $html,
            $mailtoMatches
        );

        foreach ($mailtoMatches[1] ?? [] as $email) {
            $candidates[] = html_entity_decode(
                (string) $email,
                ENT_QUOTES | ENT_HTML5
            );
        }

        $plainText = html_entity_decode(
            strip_tags($html),
            ENT_QUOTES | ENT_HTML5
        );

        preg_match_all(
            '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',
            $plainText,
            $textMatches
        );

        foreach ($textMatches[0] ?? [] as $email) {
            $candidates[] = (string) $email;
        }

        $results = [];

        foreach ($candidates as $candidate) {
            $email = strtolower(
                trim($candidate)
            );

            $email = rtrim(
                $email,
                ".,;:)]}>\"'"
            );

            if (!$this->isValidPublicEmail($email)) {
                continue;
            }

            $results[$email] = [
                'email' => $email,
                'type' => $this->classifyEmail($email),
                'source_url' => $sourceUrl,
            ];
        }

        return array_values($results);
    }

    private function isValidPublicEmail(
        string $email
    ): bool {
        if (
            filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            return false;
        }

        [$localPart, $domain] = explode(
            '@',
            $email,
            2
        );

        $localPart = strtolower($localPart);
        $domain = strtolower($domain);

        foreach (self::BLOCKED_PREFIXES as $blocked) {
            if (
                $localPart === $blocked
                || str_starts_with(
                    $localPart,
                    $blocked . '.'
                )
                || str_starts_with(
                    $localPart,
                    $blocked . '-'
                )
            ) {
                return false;
            }
        }

        if (
            str_contains($domain, 'example.')
            || str_contains($domain, 'example.com')
            || str_contains($domain, 'example.org')
            || str_contains($domain, 'example.net')
        ) {
            return false;
        }

        return true;
    }

    private function classifyEmail(
        string $email
    ): string {
        [$localPart] = explode('@', $email, 2);

        $localPart = strtolower($localPart);

        foreach (self::GENERIC_PREFIXES as $prefix) {
            if (
                $localPart === $prefix
                || str_starts_with(
                    $localPart,
                    $prefix . '.'
                )
                || str_starts_with(
                    $localPart,
                    $prefix . '-'
                )
            ) {
                return 'generic';
            }
        }

        if (
            preg_match(
                '/^[a-z]+[._-][a-z]+$/',
                $localPart
            ) === 1
        ) {
            return 'personal';
        }

        return 'unknown';
    }

    private function extractRelevantLinks(
        string $html,
        string $currentUrl,
        string $baseHost
    ): array {
        if (
            !class_exists(DOMDocument::class)
        ) {
            return [];
        }

        libxml_use_internal_errors(true);

        $document = new DOMDocument();

        $loaded = $document->loadHTML(
            $html,
            LIBXML_NOWARNING | LIBXML_NOERROR
        );

        libxml_clear_errors();

        if (!$loaded) {
            return [];
        }

        $links = [];

        foreach (
            $document->getElementsByTagName('a')
            as $anchor
        ) {
            $href = trim(
                $anchor->getAttribute('href')
            );

            if (
                $href === ''
                || str_starts_with($href, '#')
                || str_starts_with(
                    strtolower($href),
                    'mailto:'
                )
                || str_starts_with(
                    strtolower($href),
                    'tel:'
                )
                || str_starts_with(
                    strtolower($href),
                    'javascript:'
                )
            ) {
                continue;
            }

            $label = strtolower(
                trim($anchor->textContent)
            );

            $haystack = strtolower(
                $href . ' ' . $label
            );

            if (
                !str_contains($haystack, 'contact')
                && !str_contains($haystack, 'mention')
                && !str_contains($haystack, 'legal')
                && !str_contains($haystack, 'a-propos')
                && !str_contains($haystack, 'about')
            ) {
                continue;
            }

            $absoluteUrl = $this->toAbsoluteUrl(
                $currentUrl,
                $href
            );

            if (
                $absoluteUrl === null
                || !$this->isSameHost(
                    $absoluteUrl,
                    $baseHost
                )
            ) {
                continue;
            }

            $links[] = $absoluteUrl;
        }

        return array_values(
            array_unique($links)
        );
    }

    private function toAbsoluteUrl(
        string $currentUrl,
        string $href
    ): ?string {
        if (
            preg_match(
                '#^https?://#i',
                $href
            )
        ) {
            return $href;
        }

        $parts = parse_url($currentUrl);

        if (
            $parts === false
            || !isset(
                $parts['scheme'],
                $parts['host']
            )
        ) {
            return null;
        }

        $base = $parts['scheme']
            . '://'
            . $parts['host'];

        if (str_starts_with($href, '//')) {
            return $parts['scheme']
                . ':'
                . $href;
        }

        if (str_starts_with($href, '/')) {
            return rtrim($base, '/')
                . $href;
        }

        $path = $parts['path'] ?? '/';

        $directory = rtrim(
            str_replace(
                '\\',
                '/',
                dirname($path)
            ),
            '/'
        );

        return rtrim($base, '/')
            . ($directory !== '' ? $directory : '')
            . '/'
            . ltrim($href, '/');
    }

    private function extractHost(
        string $url
    ): ?string {
        $host = parse_url(
            $url,
            PHP_URL_HOST
        );

        if (!is_string($host) || $host === '') {
            return null;
        }

        return strtolower(
            preg_replace(
                '/^www\./i',
                '',
                $host
            ) ?? $host
        );
    }

    private function isSameHost(
        string $url,
        string $baseHost
    ): bool {
        $host = $this->extractHost($url);

        return $host !== null
            && $host === $baseHost;
    }
}
