<?php

declare(strict_types=1);

final class AiAuditGenerator
{
    private const API_URL =
        'https://api.openai.com/v1/responses';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'gpt-5.6-luna'
    ) {
        if (trim($this->apiKey) === '') {
            throw new InvalidArgumentException(
                'Clé API OpenAI absente.'
            );
        }
    }

    public function generate(
        array $prospect,
        array $analysis,
        array $scoring
    ): array {
        $payload = $this->buildPayload(
            $prospect,
            $analysis,
            $scoring
        );

        $response = $this->request($payload);

        return $this->extractAudit($response);
    }

    private function buildPayload(
        array $prospect,
        array $analysis,
        array $scoring
    ): array {
        $verifiedData = [
            'prospect' => [
                'company_name' =>
                    $prospect['company_name'] ?? null,

                'activity' =>
                    $prospect['activity'] ?? null,

                'city' =>
                    $prospect['city'] ?? null,

                'postal_code' =>
                    $prospect['postal_code'] ?? null,

                'website_url' =>
                    $prospect['website_url'] ?? null,

                'email_type' =>
                    $prospect['email_type'] ?? null,
            ],

            'website_analysis' => [
                'website_reachable' =>
                    $analysis['website_reachable'] ?? null,

                'http_status' =>
                    $analysis['http_status'] ?? null,

                'has_ssl' =>
                    $analysis['has_ssl'] ?? null,

                'response_time_ms' =>
                    $analysis['response_time_ms'] ?? null,

                'html_size_bytes' =>
                    $analysis['html_size_bytes'] ?? null,

                'title' =>
                    $analysis['title'] ?? null,

                'has_title' =>
                    $analysis['has_title'] ?? null,

                'has_meta_description' =>
                    $analysis['has_meta_description'] ?? null,

                'h1_count' =>
                    $analysis['h1_count'] ?? null,

                'has_viewport' =>
                    $analysis['has_viewport'] ?? null,

                'has_canonical' =>
                    $analysis['has_canonical'] ?? null,

                'has_open_graph' =>
                    $analysis['has_open_graph'] ?? null,

                'has_json_ld' =>
                    $analysis['has_json_ld'] ?? null,

                'has_contact_page' =>
                    $analysis['has_contact_page'] ?? null,

                'has_contact_form' =>
                    $analysis['has_contact_form'] ?? null,

                'has_visible_email' =>
                    $analysis['has_visible_email'] ?? null,

                'has_visible_phone' =>
                    $analysis['has_visible_phone'] ?? null,

                'has_social_links' =>
                    $analysis['has_social_links'] ?? null,

                'has_favicon' =>
                    $analysis['has_favicon'] ?? null,

                'robots_txt' =>
                    $analysis['robots_txt'] ?? null,

                'sitemap_xml' =>
                    $analysis['sitemap_xml'] ?? null,

                'technologies' =>
                    $analysis['technologies'] ?? [],
            ],

            'scoring' => [
                'score' =>
                    $scoring['score'] ?? null,

                'threshold' =>
                    $scoring['threshold'] ?? null,

                'qualified' =>
                    $scoring['qualified'] ?? null,

                'breakdown' =>
                    $scoring['breakdown'] ?? [],
            ],
        ];

        return [
            'model' => $this->model,

            'reasoning' => [
                'effort' => 'low',
            ],

            'instructions' =>
                $this->systemInstructions(),

            'input' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' =>
                                "Voici les seules données vérifiées utilisables :\n"
                                . json_encode(
                                    $verifiedData,
                                    JSON_THROW_ON_ERROR
                                    | JSON_UNESCAPED_UNICODE
                                    | JSON_UNESCAPED_SLASHES
                                    | JSON_PRETTY_PRINT
                                ),
                        ],
                    ],
                ],
            ],

            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'prospect_audit',
                    'strict' => true,

                    'schema' => [
                        'type' => 'object',

                        'properties' => [
                            'summary' => [
                                'type' => 'string',
                            ],

                            'strengths' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'string',
                                ],
                            ],

                            'weaknesses' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'string',
                                ],
                            ],

                            'opportunities' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'string',
                                ],
                            ],

                            'recommended_angle' => [
                                'type' => 'string',
                            ],

                            'audit_text' => [
                                'type' => 'string',
                            ],
                        ],

                        'required' => [
                            'summary',
                            'strengths',
                            'weaknesses',
                            'opportunities',
                            'recommended_angle',
                            'audit_text',
                        ],

                        'additionalProperties' => false,
                    ],
                ],
            ],
        ];
    }

    private function systemInstructions(): string
    {
        return <<<'TEXT'
Tu rédiges un audit commercial B2B pour OL Creative Studio.

RÈGLE ABSOLUE :
Tu dois utiliser uniquement les données vérifiées fournies dans l'entrée.

Tu ne dois jamais :
- inventer le nom d'un dirigeant ;
- inventer une ancienneté ;
- inventer un chiffre d'affaires ;
- inventer une collaboration passée ;
- inventer un problème qui n'est pas établi ;
- prétendre qu'un site est lent uniquement à partir d'un temps de réponse HTTP ;
- prétendre qu'un site est responsive uniquement grâce à une balise viewport ;
- inventer des résultats futurs ;
- inventer des statistiques de trafic ;
- inventer un manque de clients ;
- inventer une urgence commerciale ;
- prétendre avoir effectué un audit visuel complet si seules des données techniques sont disponibles.

Quand une donnée n'est pas démontrée, ne l'utilise pas.

Le ton doit être :
- professionnel ;
- concret ;
- sobre ;
- non agressif ;
- utile à une future prise de contact commerciale.

Les faiblesses doivent être formulées comme des constats techniques observables.

Les opportunités doivent être formulées comme des pistes d'amélioration, jamais comme des promesses de résultat.

L'audit_text doit être rédigé en français et rester concis.
TEXT;
    }

    private function request(array $payload): array
    {
        $curl = curl_init(self::API_URL);

        if ($curl === false) {
            throw new RuntimeException(
                'Impossible d’initialiser cURL.'
            );
        }

        curl_setopt_array(
            $curl,
            [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 120,

                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer '
                        . $this->apiKey,

                    'Content-Type: application/json',
                ],

                CURLOPT_POSTFIELDS =>
                    json_encode(
                        $payload,
                        JSON_THROW_ON_ERROR
                        | JSON_UNESCAPED_UNICODE
                        | JSON_UNESCAPED_SLASHES
                    ),
            ]
        );

        $body = curl_exec($curl);

        if ($body === false) {
            $error = curl_error($curl);

            unset($curl);

            throw new RuntimeException(
                'Erreur OpenAI : ' . $error
            );
        }

        $statusCode = (int) curl_getinfo(
            $curl,
            CURLINFO_RESPONSE_CODE
        );

        unset($curl);

        $decoded = json_decode(
            $body,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if ($statusCode < 200 || $statusCode >= 300) {
            $message =
                $decoded['error']['message']
                ?? 'Erreur API OpenAI inconnue.';

            throw new RuntimeException(
                'OpenAI HTTP '
                . $statusCode
                . ' : '
                . $message
            );
        }

        return $decoded;
    }

    private function extractAudit(
        array $response
    ): array {
        $output = $response['output'] ?? null;

        if (!is_array($output)) {
            throw new RuntimeException(
                'Réponse OpenAI invalide : output absent.'
            );
        }

        foreach ($output as $item) {
            if (
                !is_array($item)
                || ($item['type'] ?? null) !== 'message'
            ) {
                continue;
            }

            $content = $item['content'] ?? null;

            if (!is_array($content)) {
                continue;
            }

            foreach ($content as $contentItem) {
                if (!is_array($contentItem)) {
                    continue;
                }

                if (
                    ($contentItem['type'] ?? null)
                    !== 'output_text'
                ) {
                    continue;
                }

                $text =
                    $contentItem['text'] ?? null;

                if (!is_string($text)) {
                    continue;
                }

                $audit = json_decode(
                    $text,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

                if (!is_array($audit)) {
                    break;
                }

                return $audit;
            }
        }

        throw new RuntimeException(
            'Aucun audit exploitable retourné par OpenAI.'
        );
    }
}
