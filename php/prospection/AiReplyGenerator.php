<?php

declare(strict_types=1);

require_once __DIR__ . '/ReplyGeneratorInterface.php';

final class AiReplyGenerator implements ReplyGeneratorInterface
{
    private const API_URL =
        'https://api.openai.com/v1/responses';

    private const ALLOWED_CLASSIFICATIONS = [
        'positive',
        'question',
    ];

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
        array $reply,
        array $outboundEmail
    ): array {
        $classification =
            $this->validateClassification(
                $reply['classification'] ?? null
            );

        $replyContent =
            $this->requireString(
                $reply['body_text'] ?? null,
                'Contenu de la réponse absent.'
            );

        $payload = $this->buildPayload(
            $prospect,
            $replyContent,
            $classification,
            $outboundEmail
        );

        $response =
            $this->request($payload);

        return $this->extractMessage(
            $response
        );
    }

    private function buildPayload(
        array $prospect,
        string $replyContent,
        string $classification,
        array $outboundEmail
    ): array {
        /*
         * On transmet uniquement le contexte
         * nécessaire à la rédaction.
         *
         * L'adresse e-mail du prospect n'est
         * volontairement pas envoyée au modèle.
         */
        $verifiedData = [
            'company_name' =>
                $prospect['company_name'] ?? null,

            'activity' =>
                $prospect['activity'] ?? null,

            'city' =>
                $prospect['city'] ?? null,

            'reply_classification' =>
                $classification,

            'prospect_reply' =>
                $replyContent,

            'previous_email' => [
                'subject' =>
                    $outboundEmail['subject'] ?? null,

                'body_text' =>
                    $outboundEmail['body_text'] ?? null,
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
                            'type' =>
                                'input_text',

                            'text' =>
                                "Contexte vérifié de la conversation :\n"
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

                    'name' =>
                        'prospect_reply_draft',

                    'strict' => true,

                    'schema' => [
                        'type' => 'object',

                        'properties' => [
                            'subject' => [
                                'type' => 'string',
                            ],

                            'body' => [
                                'type' => 'string',
                            ],
                        ],

                        'required' => [
                            'subject',
                            'body',
                        ],

                        'additionalProperties' =>
                            false,
                    ],
                ],
            ],
        ];
    }

    private function systemInstructions(): string
    {
        return <<<'TEXT'
Tu rédiges un brouillon de réponse à un prospect ayant répondu à un e-mail envoyé par OL Creative Studio.

IMPORTANT :
Le texte produit est uniquement un brouillon destiné à être relu, modifié et validé par un humain avant tout envoi.

OBJECTIF :
Répondre précisément au message reçu et faciliter la poursuite naturelle de la conversation commerciale.

RÈGLES ABSOLUES :
- Utilise uniquement les informations fournies.
- N'invente aucun fait.
- N'invente aucun nom d'interlocuteur.
- N'invente aucun prix.
- N'invente aucun délai.
- N'invente aucune disponibilité.
- N'invente aucune prestation qui n'a pas été mentionnée.
- N'invente aucun résultat attendu.
- Ne prétends pas qu'une action a déjà été réalisée si ce n'est pas indiqué.
- Ne considère jamais une vente comme acquise.
- Ne crée aucune urgence artificielle.
- Ne mentionne pas l'utilisation d'une intelligence artificielle.
- Ne mentionne pas les mécanismes internes de prospection.
- Ne mentionne pas la classification interne de la réponse.
- Ne propose pas automatiquement un rendez-vous à une date ou une heure précise.

SI LA RÉPONSE EST POSITIVE :
- Remercie naturellement le prospect.
- Réponds à ce qu'il dit réellement.
- Facilite la prochaine étape sans forcer la vente.
- S'il demande à être rappelé mais qu'aucune disponibilité n'est fournie, demande simplement quel créneau lui conviendrait pour que je le rappelle.

SI LA RÉPONSE EST UNE QUESTION :
- Réponds uniquement avec les informations réellement disponibles.
- Si une information nécessaire manque, indique qu'elle doit être précisée plutôt que de l'inventer.
- Une question sur un prix ne doit jamais provoquer l'invention d'un tarif.
- Une question sur un délai ne doit jamais provoquer l'invention d'un délai.

STYLE :
- Français naturel.
- Vouvoiement.
- Professionnel, humain et chaleureux.
- Court et direct.
- Pas d'emojis.
- Pas de jargon commercial inutile.
- Pas de ton agressif.
- Pas de signature : elle sera ajoutée par l'application.
- Parler au nom d'OL Creative Studio à la première personne du singulier.
- Utiliser "je" et non "nous".

OBJET :
- Conserver une logique de réponse à la conversation.
- Objet court.
- Utiliser "Re: " suivi de l'objet précédent lorsque celui-ci est disponible.

BODY :
- Deux ou trois courts paragraphes maximum.
- Répondre directement au message du prospect.
- Terminer par une prochaine étape naturelle uniquement lorsqu'elle est pertinente.
TEXT;
    }

    private function validateClassification(
        mixed $classification
    ): string {
        if (
            !is_string($classification)
            || !in_array(
                $classification,
                self::ALLOWED_CLASSIFICATIONS,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'La génération IA est réservée aux réponses '
                . 'positive ou question.'
            );
        }

        return $classification;
    }

    private function requireString(
        mixed $value,
        string $errorMessage
    ): string {
        if (!is_string($value)) {
            throw new InvalidArgumentException(
                $errorMessage
            );
        }

        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException(
                $errorMessage
            );
        }

        return $value;
    }

    private function request(
        array $payload
    ): array {
        $curl =
            curl_init(self::API_URL);

        if ($curl === false) {
            throw new RuntimeException(
                'Impossible d’initialiser cURL.'
            );
        }

        curl_setopt_array(
            $curl,
            [
                CURLOPT_POST => true,

                CURLOPT_RETURNTRANSFER =>
                    true,

                CURLOPT_CONNECTTIMEOUT =>
                    10,

                CURLOPT_TIMEOUT =>
                    120,

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

        $body =
            curl_exec($curl);

        if ($body === false) {
            $error =
                curl_error($curl);

            unset($curl);

            throw new RuntimeException(
                'Erreur OpenAI : '
                . $error
            );
        }

        $statusCode =
            (int) curl_getinfo(
                $curl,
                CURLINFO_RESPONSE_CODE
            );

        unset($curl);

        $decoded =
            json_decode(
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
        $output =
            $response['output']
            ?? null;

        if (!is_array($output)) {
            throw new RuntimeException(
                'Réponse OpenAI invalide : output absent.'
            );
        }

        foreach ($output as $item) {
            if (
                !is_array($item)
                || ($item['type'] ?? null)
                    !== 'message'
            ) {
                continue;
            }

            $content =
                $item['content']
                ?? null;

            if (!is_array($content)) {
                continue;
            }

            foreach (
                $content
                as $contentItem
            ) {
                if (
                    !is_array($contentItem)
                    || ($contentItem['type'] ?? null)
                        !== 'output_text'
                ) {
                    continue;
                }

                $text =
                    $contentItem['text']
                    ?? null;

                if (!is_string($text)) {
                    continue;
                }

                $message =
                    json_decode(
                        $text,
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    );

                if (
                    !is_array($message)
                    || !isset(
                        $message['subject'],
                        $message['body']
                    )
                    || !is_string(
                        $message['subject']
                    )
                    || !is_string(
                        $message['body']
                    )
                    || trim(
                        $message['subject']
                    ) === ''
                    || trim(
                        $message['body']
                    ) === ''
                ) {
                    throw new RuntimeException(
                        'Brouillon OpenAI invalide.'
                    );
                }

                return [
                    'subject' =>
                        trim(
                            $message['subject']
                        ),

                    'body' =>
                        trim(
                            $message['body']
                        ),
                ];
            }
        }

        throw new RuntimeException(
            'Aucun brouillon exploitable retourné par OpenAI.'
        );
    }
}
