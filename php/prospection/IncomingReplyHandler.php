<?php

declare(strict_types=1);

final class IncomingReplyHandler
{
    public function __construct(
        private readonly IncomingReplyMatcher $matcher,
        private readonly IncomingReplyProcessor $processor,
        private readonly ReplyContentExtractor $contentExtractor,
        private readonly UnsubscribeManager $unsubscribeManager,
        private readonly AutomaticReplyDetector $automaticReplyDetector
    ) {
    }

    public function handle(
        array $incomingMessage
    ): array {
        $match = $this->matcher->match(
            $this->nullableString(
                $incomingMessage['in_reply_to']
                    ?? null
            ),
            $this->normalizeReferences(
                $incomingMessage['references']
                    ?? []
            ),
            $this->nullableString(
                $incomingMessage['sender_email']
                    ?? null
            )
        );

        if ($match === null) {
            return [
                'handled' => false,
                'matched' => false,
                'created' => false,
                'duplicate' => false,
                'reason' => 'no_match',
            ];
        }

        /*
         * On isole uniquement le nouveau contenu
         * avant toute classification.
         */
        $replyContent =
            $this->contentExtractor->extract(
                $incomingMessage
            );

        /*
         * Classification automatique.
         *
         * Pour le moment :
         * - automatic si une auto-réponse est détectée ;
         * - unknown sinon.
         *
         * Les classifications positive / negative /
         * question seront ajoutées plus tard.
         */
        $classification = 'unknown';

        if (
            $this->automaticReplyDetector
                ->isAutomaticReply(
                    $incomingMessage,
                    $replyContent
                )
        ) {
            $classification = 'automatic';
        }

        /*
         * Persistance de la réponse.
         *
         * Le processor applique un comportement
         * spécifique à la classification automatic :
         * - pas de passage en replied ;
         * - pas de replied_at ;
         * - pas d'annulation des relances.
         */
        $result = $this->processor->process(
            $incomingMessage,
            $match,
            $classification
        );

        $unsubscribeResult = null;

        /*
         * Une auto-réponse ne doit jamais être
         * interprétée comme une désinscription.
         *
         * Cela évite notamment qu'un footer cité
         * contenant STOP déclenche le blacklistage.
         */
        if (
            (bool) ($result['created'] ?? false)
            && ($result['classification'] ?? null)
                !== 'automatic'
            && isset($result['reply_id'])
            && isset($result['prospect_id'])
        ) {
            if (
                $this->unsubscribeManager
                    ->isUnsubscribeRequest(
                        $replyContent
                    )
            ) {
                $senderEmail =
                    $this->nullableString(
                        $incomingMessage[
                            'sender_email'
                        ] ?? null
                    );

                $receivedAt =
                    $this->nullableString(
                        $incomingMessage[
                            'received_at'
                        ] ?? null
                    );

                if (
                    $senderEmail === null
                    || $receivedAt === null
                ) {
                    throw new RuntimeException(
                        'Données de désinscription incomplètes.'
                    );
                }

                $unsubscribeResult =
                    $this->unsubscribeManager
                        ->unsubscribe(
                            (int) $result['prospect_id'],
                            (int) $result['reply_id'],
                            $senderEmail,
                            $replyContent,
                            $receivedAt
                        );
            }
        }

        return [
            'handled' => true,
            'matched' => true,

            'created' =>
                (bool) ($result['created'] ?? false),

            'duplicate' =>
                (bool) ($result['duplicate'] ?? false),

            'match_type' =>
                $match['match_type'] ?? null,

            'prospect_id' =>
                isset($result['prospect_id'])
                    ? (int) $result['prospect_id']
                    : null,

            'reply_id' =>
                isset($result['reply_id'])
                    ? (int) $result['reply_id']
                    : null,

            'classification' =>
                $result['classification']
                    ?? null,

            'automatic' =>
                ($result['classification'] ?? null)
                    === 'automatic',

            'unsubscribed' =>
                $unsubscribeResult !== null,

            'unsubscribe_result' =>
                $unsubscribeResult,

            'result' => $result,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function normalizeReferences(
        mixed $references
    ): array {
        if (!is_array($references)) {
            return [];
        }

        $normalized = [];

        foreach ($references as $reference) {
            if (!is_string($reference)) {
                continue;
            }

            $reference = trim($reference);

            if ($reference !== '') {
                $normalized[] = $reference;
            }
        }

        return array_values(
            array_unique($normalized)
        );
    }

    private function nullableString(
        mixed $value
    ): ?string {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== ''
            ? $value
            : null;
    }
}
