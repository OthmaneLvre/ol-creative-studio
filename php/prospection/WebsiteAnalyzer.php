<?php

declare(strict_types=1);

final class WebsiteAnalyzer
{
    private const SOCIAL_DOMAINS = [
        'facebook.com',
        'instagram.com',
        'linkedin.com',
        'youtube.com',
        'tiktok.com',
        'x.com',
        'twitter.com',
        'pinterest.com',
    ];

    public function __construct(
        private readonly int $timeout = 15
    ) {
    }

    public function analyze(string $websiteUrl): array
    {
        $url = $this->normalizeUrl($websiteUrl);

        $result = [
            'website_url' => $url,
            'website_reachable' => false,
            'http_status' => null,
            'final_url' => null,
            'has_ssl' => false,
            'response_time_ms' => null,
            'html_size_bytes' => null,

            'title' => null,
            'title_length' => 0,
            'has_title' => false,

            'meta_description' => null,
            'meta_description_length' => 0,
            'has_meta_description' => false,

            'h1_count' => 0,
            'has_h1' => false,

            'has_viewport' => false,
            'is_mobile_friendly' => false,

            'has_canonical' => false,
            'canonical_url' => null,

            'has_open_graph' => false,
            'has_json_ld' => false,

            'has_contact_page' => false,
            'has_contact_form' => false,

            'has_visible_email' => false,
            'has_visible_phone' => false,

            'has_social_links' => false,
            'social_links' => [],

            'has_favicon' => false,

            'robots_txt' => false,
            'sitemap_xml' => false,

            'technologies' => [],

            'analyzed_at' => date('Y-m-d H:i:s'),
        ];

        try {
            $page = $this->fetch($url);
        } catch (Throwable $exception) {
            $result['error'] = $exception->getMessage();

            return $result;
        }

        $result['http_status'] = $page['http_status'];
        $result['final_url'] = $page['final_url'];
        $result['response_time_ms'] = $page['response_time_ms'];
        $result['html_size_bytes'] = strlen($page['body']);

        $result['website_reachable'] =
            $page['http_status'] >= 200
            && $page['http_status'] < 400;

        $result['has_ssl'] =
            str_starts_with(
                strtolower($page['final_url']),
                'https://'
            );

        if (!$result['website_reachable']) {
            return $result;
        }

        $html = $page['body'];

        $document = $this->createDomDocument($html);

        if ($document !== null) {
            $xpath = new DOMXPath($document);

            $this->analyzeTitle(
                $xpath,
                $result
            );

            $this->analyzeMetaDescription(
                $xpath,
                $result
            );

            $this->analyzeHeadings(
                $xpath,
                $result
            );

            $this->analyzeViewport(
                $xpath,
                $result
            );

            $this->analyzeCanonical(
                $xpath,
                $result
            );

            $this->analyzeOpenGraph(
                $xpath,
                $result
            );

            $this->analyzeStructuredData(
                $xpath,
                $result
            );

            $this->analyzeForms(
                $xpath,
                $result
            );

            $this->analyzeLinks(
                $xpath,
                $page['final_url'],
                $result
            );

            $this->analyzeFavicon(
                $xpath,
                $result
            );
        }

        $this->analyzeVisibleContactData(
            $html,
            $result
        );

        $result['technologies'] =
            $this->detectTechnologies(
                $html,
                $page['headers']
            );

        $baseUrl =
            $this->getBaseUrl(
                $page['final_url']
            );

        $result['robots_txt'] =
            $this->resourceExists(
                $baseUrl . '/robots.txt'
            );

        $result['sitemap_xml'] =
            $this->resourceExists(
                $baseUrl . '/sitemap.xml'
            );

        return $result;
    }

    private function normalizeUrl(
        string $websiteUrl
    ): string {
        $websiteUrl = trim($websiteUrl);

        if ($websiteUrl === '') {
            throw new InvalidArgumentException(
                'URL du site obligatoire.'
            );
        }

        if (
            !preg_match(
                '#^https?://#i',
                $websiteUrl
            )
        ) {
            $websiteUrl =
                'https://' . $websiteUrl;
        }

        if (
            filter_var(
                $websiteUrl,
                FILTER_VALIDATE_URL
            ) === false
        ) {
            throw new InvalidArgumentException(
                'URL du site invalide.'
            );
        }

        return $websiteUrl;
    }

    private function fetch(
        string $url
    ): array {
        $curl = curl_init($url);

        if ($curl === false) {
            throw new RuntimeException(
                'Impossible d’initialiser la requête HTTP.'
            );
        }

        $headers = [];

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT =>
                'OL-Creative-Studio-Prospection/1.0',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml',
            ],
            CURLOPT_HEADERFUNCTION =>
                static function (
                    $curl,
                    string $header
                ) use (&$headers): int {
                    $length = strlen($header);

                    $header = trim($header);

                    if (
                        $header === ''
                        || !str_contains(
                            $header,
                            ':'
                        )
                    ) {
                        return $length;
                    }

                    [$name, $value] =
                        explode(
                            ':',
                            $header,
                            2
                        );

                    $headers[
                        strtolower(trim($name))
                    ] = trim($value);

                    return $length;
                },
        ]);

        $startedAt = microtime(true);

        $body = curl_exec($curl);

        $endedAt = microtime(true);

        if ($body === false) {
            $error = curl_error($curl);

            unset($curl);

            throw new RuntimeException(
                'Erreur HTTP : ' . $error
            );
        }

        $httpStatus =
            (int) curl_getinfo(
                $curl,
                CURLINFO_RESPONSE_CODE
            );

        $finalUrl =
            (string) curl_getinfo(
                $curl,
                CURLINFO_EFFECTIVE_URL
            );

        unset($curl);

        return [
            'body' => (string) $body,
            'http_status' => $httpStatus,
            'final_url' =>
                $finalUrl !== ''
                    ? $finalUrl
                    : $url,
            'response_time_ms' =>
                (int) round(
                    ($endedAt - $startedAt)
                    * 1000
                ),
            'headers' => $headers,
        ];
    }

    private function createDomDocument(
        string $html
    ): ?DOMDocument {
        if (
            !class_exists(
                DOMDocument::class
            )
        ) {
            return null;
        }

        libxml_use_internal_errors(true);

        $document = new DOMDocument();

        $loaded =
            $document->loadHTML(
                $html,
                LIBXML_NOWARNING
                | LIBXML_NOERROR
            );

        libxml_clear_errors();

        return $loaded
            ? $document
            : null;
    }

    private function analyzeTitle(
        DOMXPath $xpath,
        array &$result
    ): void {
        $nodes =
            $xpath->query('//title');

        if (
            $nodes === false
            || $nodes->length === 0
        ) {
            return;
        }

        $title =
            trim(
                $nodes->item(0)
                    ?->textContent
                ?? ''
            );

        if ($title === '') {
            return;
        }

        $result['title'] = $title;
        $result['title_length'] =
            mb_strlen($title);

        $result['has_title'] = true;
    }

    private function analyzeMetaDescription(
        DOMXPath $xpath,
        array &$result
    ): void {
        $nodes = $xpath->query(
            '//meta[
                translate(
                    @name,
                    "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
                    "abcdefghijklmnopqrstuvwxyz"
                ) = "description"
            ]'
        );

        if (
            $nodes === false
            || $nodes->length === 0
        ) {
            return;
        }

        $node = $nodes->item(0);

        if (!$node instanceof DOMElement) {
            return;
        }

        $description = trim(
            $node->getAttribute('content')
        );

        if ($description === '') {
            return;
        }

        $result['meta_description'] =
            $description;

        $result['meta_description_length'] =
            mb_strlen($description);

        $result['has_meta_description'] =
            true;
    }

    private function analyzeHeadings(
        DOMXPath $xpath,
        array &$result
    ): void {
        $nodes =
            $xpath->query('//h1');

        if ($nodes === false) {
            return;
        }

        $result['h1_count'] =
            $nodes->length;

        $result['has_h1'] =
            $nodes->length > 0;
    }

    private function analyzeViewport(
        DOMXPath $xpath,
        array &$result
    ): void {
        $nodes =
            $xpath->query(
                '//meta[
                    translate(
                        @name,
                        "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
                        "abcdefghijklmnopqrstuvwxyz"
                    ) = "viewport"
                ]'
            );

        $hasViewport =
            $nodes !== false
            && $nodes->length > 0;

        $result['has_viewport'] =
            $hasViewport;

        /*
         * V1 :
         * présence du viewport utilisée
         * comme proxy de compatibilité mobile.
         *
         * Ce n'est pas un véritable test
         * de rendu responsive.
         */
        $result['is_mobile_friendly'] =
            $hasViewport;
    }

    private function analyzeCanonical(
        DOMXPath $xpath,
        array &$result
    ): void {
        $nodes = $xpath->query(
            '//link[
                translate(
                    @rel,
                    "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
                    "abcdefghijklmnopqrstuvwxyz"
                ) = "canonical"
            ]'
        );

        if (
            $nodes === false
            || $nodes->length === 0
        ) {
            return;
        }

        $node = $nodes->item(0);

        if (!$node instanceof DOMElement) {
            return;
        }

        $href = trim(
            $node->getAttribute('href')
        );

        if ($href === '') {
            return;
        }

        $result['has_canonical'] = true;
        $result['canonical_url'] = $href;
    }

    private function analyzeOpenGraph(
        DOMXPath $xpath,
        array &$result
    ): void {
        $nodes =
            $xpath->query(
                '//meta[
                    starts-with(
                        translate(
                            @property,
                            "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
                            "abcdefghijklmnopqrstuvwxyz"
                        ),
                        "og:"
                    )
                ]'
            );

        $result['has_open_graph'] =
            $nodes !== false
            && $nodes->length > 0;
    }

    private function analyzeStructuredData(
        DOMXPath $xpath,
        array &$result
    ): void {
        $nodes =
            $xpath->query(
                '//script[
                    translate(
                        @type,
                        "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
                        "abcdefghijklmnopqrstuvwxyz"
                    ) = "application/ld+json"
                ]'
            );

        $result['has_json_ld'] =
            $nodes !== false
            && $nodes->length > 0;
    }

    private function analyzeForms(
        DOMXPath $xpath,
        array &$result
    ): void {
        $forms = $xpath->query('//form');

        if (
            $forms === false
            || $forms->length === 0
        ) {
            return;
        }

        foreach ($forms as $form) {
            if (!$form instanceof DOMElement) {
                continue;
            }

            $text = strtolower(
                $form->textContent
            );

            $document = $form->ownerDocument;

            $html = '';

            if ($document instanceof DOMDocument) {
                $savedHtml =
                    $document->saveHTML($form);

                if (is_string($savedHtml)) {
                    $html = strtolower(
                        $savedHtml
                    );
                }
            }

            if (
                str_contains($text, 'contact')
                || str_contains($text, 'message')
                || str_contains($html, 'email')
                || str_contains($html, 'message')
            ) {
                $result['has_contact_form'] =
                    true;

                return;
            }
        }
    }

    private function analyzeLinks(
        DOMXPath $xpath,
        string $currentUrl,
        array &$result
    ): void {
        $links = $xpath->query(
            '//a[@href]'
        );

        if ($links === false) {
            return;
        }

        $socialLinks = [];

        foreach ($links as $link) {
            if (!$link instanceof DOMElement) {
                continue;
            }

            $href = trim(
                $link->getAttribute('href')
            );

            if ($href === '') {
                continue;
            }

            $lowerHref = strtolower($href);

            $label = strtolower(
                trim($link->textContent)
            );

            $contactHaystack =
                $lowerHref
                . ' '
                . $label;

            if (
                str_contains(
                    $contactHaystack,
                    'contact'
                )
            ) {
                $result['has_contact_page'] =
                    true;
            }

            foreach (
                self::SOCIAL_DOMAINS
                as $domain
            ) {
                if (
                    str_contains(
                        $lowerHref,
                        $domain
                    )
                ) {
                    $socialLinks[] = $href;

                    break;
                }
            }
        }

        $socialLinks = array_values(
            array_unique($socialLinks)
        );

        $result['social_links'] =
            $socialLinks;

        $result['has_social_links'] =
            $socialLinks !== [];
    }

    private function analyzeFavicon(
        DOMXPath $xpath,
        array &$result
    ): void {
        $nodes =
            $xpath->query(
                '//link[
                    contains(
                        translate(
                            @rel,
                            "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
                            "abcdefghijklmnopqrstuvwxyz"
                        ),
                        "icon"
                    )
                ]'
            );

        $result['has_favicon'] =
            $nodes !== false
            && $nodes->length > 0;
    }

    private function analyzeVisibleContactData(
        string $html,
        array &$result
    ): void {
        $decoded =
            html_entity_decode(
                $html,
                ENT_QUOTES | ENT_HTML5
            );

        $result['has_visible_email'] =
            preg_match(
                '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',
                $decoded
            ) === 1;

        $result['has_visible_phone'] =
            preg_match(
                '/(?:(?:\+33|0033|0)[1-9](?:[\s.\-]?\d{2}){4})/',
                strip_tags($decoded)
            ) === 1;
    }

    private function detectTechnologies(
        string $html,
        array $headers
    ): array {
        $htmlLower =
            strtolower($html);

        $technologies = [];

        if (
            str_contains(
                $htmlLower,
                'wp-content'
            )
            || str_contains(
                $htmlLower,
                'wp-includes'
            )
        ) {
            $technologies[] =
                'WordPress';
        }

        if (
            str_contains(
                $htmlLower,
                'wixstatic.com'
            )
        ) {
            $technologies[] =
                'Wix';
        }

        if (
            str_contains(
                $htmlLower,
                'cdn.shopify.com'
            )
            || str_contains(
                $htmlLower,
                'shopify'
            )
        ) {
            $technologies[] =
                'Shopify';
        }

        if (
            str_contains(
                $htmlLower,
                'site-solocal.com'
            )
            || str_contains(
                $htmlLower,
                'solocal'
            )
        ) {
            $technologies[] =
                'Solocal';
        }

        if (
            isset($headers['server'])
            && trim(
                $headers['server']
            ) !== ''
        ) {
            $technologies[] =
                'Server: '
                . trim(
                    $headers['server']
                );
        }

        return array_values(
            array_unique(
                $technologies
            )
        );
    }

    private function getBaseUrl(
        string $url
    ): string {
        $parts =
            parse_url($url);

        if (
            $parts === false
            || !isset(
                $parts['scheme'],
                $parts['host']
            )
        ) {
            throw new RuntimeException(
                'URL finale invalide.'
            );
        }

        return $parts['scheme']
            . '://'
            . $parts['host'];
    }

    private function resourceExists(
        string $url
    ): bool {
        $curl =
            curl_init($url);

        if ($curl === false) {
            return false;
        }

        curl_setopt_array($curl, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_USERAGENT =>
                'OL-Creative-Studio-Prospection/1.0',
        ]);

        curl_exec($curl);

        $status =
            (int) curl_getinfo(
                $curl,
                CURLINFO_RESPONSE_CODE
            );

        unset($curl);

        return $status >= 200
            && $status < 400;
    }
}
