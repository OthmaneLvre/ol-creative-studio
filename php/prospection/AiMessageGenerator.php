<?php

declare(strict_types=1);

final class AiMessageGenerator
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
        array $audit
    ): array {
        $payload = $this->buildPayload(
            $prospect,
            $audit
        );

        $response = $this->request($payload);

        return $this->extractMessage($response);
    }

    private function buildPayload(
        array $prospect,
        array $audit
    ): array {
        /*
         * On transmet uniquement les informations
         * nécessaires à la rédaction.
         *
         * L'adresse e-mail du prospect n'est même
         * pas nécessaire au modèle.
         */
        $verifiedData = [
            'company_name' =>
                $prospect['company_name'] ?? null,

            'activity' =>
                $prospect['activity'] ?? null,

            'city' =>
                $prospect['city'] ?? null,

            'website_url' =>
                $prospect['website_url'] ?? null,

            'audit' => [
                'summary' =>
                    $audit['summary'] ?? null,

                'strengths' =>
                    $audit['strengths'] ?? [],

                'weaknesses' =>
                    $audit['weaknesses'] ?? [],

                'opportunities' =>
                    $audit['opportunities'] ?? [],

                'recommended_angle' =>
                    $audit['recommended_angle'] ?? null,
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
                                "Données vérifiées du prospect et audit :\n"
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
                    'name' => 'prospect_initial_email',
                    'strict' => true,

                    'schema' => [
                        'type' => 'object',

                        'properties' => [
                            'subject' => [
                                'type' => 'string',
                            ],

                            'preheader' => [
                                'type' => 'string',
                            ],

                            'opening' => [
                                'type' => 'string',
                            ],

                            'body' => [
                                'type' => 'string',
                            ],

                            'cta' => [
                                'type' => 'string',
                            ],
                        ],

                        'required' => [
                            'subject',
                            'preheader',
                            'opening',
                            'body',
                            'cta',
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
Tu rédiges le premier e-mail de prospection B2B d'OL Creative Studio.

CONTEXTE :
OL Creative Studio propose des services de création et d'amélioration de sites web pour les entreprises locales.

OBJECTIF :
Obtenir une réponse du prospect en lui proposant un mini-audit gratuit de sa présence en ligne.

RÈGLES ABSOLUES :
- Utilise uniquement les données vérifiées fournies.
- N'invente jamais le nom d'un dirigeant ou d'un interlocuteur.
- N'invente aucune statistique.
- N'invente aucun problème commercial.
- N'invente aucune perte de clients.
- N'invente aucun résultat futur.
- Ne prétends jamais avoir travaillé avec l'entreprise.
- Ne crée aucune urgence artificielle.
- Ne critique pas agressivement le site.
- Ne prétends pas qu'un site n'est pas responsive si cela n'a pas été vérifié.
- Ne prétends pas qu'un site est lent à partir d'un simple temps de réponse HTTP.
- Ne mentionne pas le score interne de qualification.
- Ne mentionne pas l'utilisation d'une intelligence artificielle.
- Ne mentionne pas les mécanismes internes de prospection.

STYLE :
- Français naturel.
- Ton local, professionnel et chaleureux.
- Tutoiement interdit.
- Pas de jargon inutile.
- Pas de ton marketing agressif.
- Pas d'emojis.
- Message court.
- Maximum deux constats techniques concrets.
- Valoriser ce qui fonctionne déjà avant d'évoquer une amélioration.
- Le destinataire doit comprendre rapidement pourquoi il est contacté.

CTA :
Proposer un mini-audit gratuit, sans engagement.
Le CTA doit demander simplement si le prospect souhaite le recevoir.

OBJET :
Court, naturel et non sensationnaliste.
Éviter les formulations ressemblant à du spam.
L'objet doit indiquer clairement qu'il s'agit du site ou de la présence en ligne.
Éviter les objets trop vagues comme « Votre site à Céret ».
Privilégier un objet naturel de 4 à 8 mots.

PREHEADER :
Une phrase courte complémentaire à l'objet.

OPENING :
Une courte introduction personnalisée à l'entreprise et/ou à sa localisation.

BODY :
Deux courts paragraphes maximum.

CTA :
Une phrase courte sous forme de question.

Ne génère pas de signature.
La signature OL Creative Studio sera ajoutée automatiquement par le template PHP.
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

        if (
            $statusCode < 200
            || $statusCode >= 300
        ) {
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

    private function extractMessage(
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
                if (
                    !is_array($contentItem)
                    || ($contentItem['type'] ?? null)
                        !== 'output_text'
                ) {
                    continue;
                }

                $text = $contentItem['text'] ?? null;

                if (!is_string($text)) {
                    continue;
                }

                $message = json_decode(
                    $text,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

                if (is_array($message)) {
                    return $message;
                }
            }
        }

        throw new RuntimeException(
            'Aucun message exploitable retourné par OpenAI.'
        );
    }
}
