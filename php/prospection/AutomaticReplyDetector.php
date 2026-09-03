<?php

declare(strict_types=1);

final class AutomaticReplyDetector
{
    public function isAutomaticReply(
        array $incomingMessage,
        string $newContent
    ): bool {
        $subject = $this->normalize(
            $incomingMessage['subject'] ?? null
        );

        $content = $this->normalize(
            $newContent
        );

        if (
            $this->matchesAutomaticSubject(
                $subject
            )
        ) {
            return true;
        }

        if (
            $this->matchesAutomaticContent(
                $content
            )
        ) {
            return true;
        }

        return false;
    }

    private function matchesAutomaticSubject(
        string $subject
    ): bool {
        if ($subject === '') {
            return false;
        }

        $patterns = [
            '/\babsence du bureau\b/iu',
            '/\bréponse automatique\b/iu',
            '/\breponse automatique\b/iu',
            '/\bmessage automatique\b/iu',
            '/\baccusé de réception\b/iu',
            '/\baccuse de reception\b/iu',
            '/\bout of office\b/iu',
            '/\bautomatic reply\b/iu',
            '/\bauto(?:matic)? reply\b/iu',
            '/\bautoreply\b/iu',
            '/\bauto-reply\b/iu',
        ];

        foreach ($patterns as $pattern) {
            if (
                preg_match(
                    $pattern,
                    $subject
                ) === 1
            ) {
                return true;
            }
        }

        return false;
    }

    private function matchesAutomaticContent(
        string $content
    ): bool {
        if ($content === '') {
            return false;
        }

        $patterns = [
            '/\bje suis actuellement absent(?:e)?(?: du bureau)?\b/iu',
            '/\bje serai absent(?:e)? jusqu(?:’|\')au\b/iu',
            '/\bje suis en congé(?:s)?\b/iu',
            '/\bje suis en conge(?:s)?\b/iu',
            '/\bnous avons bien reçu votre (?:message|e-mail|email|mail)\b/iu',
            '/\bvotre message a bien été reçu\b/iu',
            '/\bvotre message a bien ete recu\b/iu',
            '/\bceci est (?:un|une) (?:message|réponse|reponse) automatique\b/iu',
            '/\bmessage généré automatiquement\b/iu',
            '/\bmessage genere automatiquement\b/iu',
            '/\bi am currently out of (?:the )?office\b/iu',
            '/\bi am away from (?:the )?office\b/iu',
            '/\bi will be out of (?:the )?office\b/iu',
            '/\bthank you for your email\.? i am currently away\b/iu',
            '/\bthis is an automatic(?:ally generated)? (?:reply|response|message)\b/iu',
        ];

        foreach ($patterns as $pattern) {
            if (
                preg_match(
                    $pattern,
                    $content
                ) === 1
            ) {
                return true;
            }
        }

        return false;
    }

    private function normalize(
        mixed $value
    ): string {
        if (!is_string($value)) {
            return '';
        }

        $value = trim($value);

        if ($value === '') {
            return '';
        }

        return preg_replace(
            '/[ \t]+/u',
            ' ',
            $value
        ) ?? $value;
    }
}
