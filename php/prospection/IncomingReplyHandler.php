<?php

declare(strict_types=1);

final class IncomingReplyHandler
{
    public function __construct(
        private readonly IncomingReplyMatcher $matcher,
        private readonly IncomingReplyProcessor $processor
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

        $result = $this->processor->process(
            $incomingMessage,
            $match
        );

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
