<?php

declare(strict_types=1);

final class HumanReplyClassifier
{
    public function classify(string $content): string
    {
        $content = $this->normalize($content);

        if ($content === '') {
            return 'unknown';
        }

        if ($this->isNegative($content)) {
            return 'negative';
        }

        if ($this->isPositive($content)) {
            return 'positive';
        }

        if ($this->isQuestion($content)) {
            return 'question';
        }

        return 'unknown';
    }

    private function isPositive(string $content): bool
    {
        $patterns = [
            '/\bje suis intéressé(?:e)?\b/iu',
            '/\bje serais intéressé(?:e)?\b/iu',
            '/\bça m(?:’|\')intéresse\b/iu',
            '/\bcela m(?:’|\')intéresse\b/iu',
            '/\bje souhaite en discuter\b/iu',
            '/\bnous pouvons en discuter\b/iu',
            '/\bappelez-moi\b/iu',
            '/\bcontactez-moi\b/iu',
            '/\bvous pouvez me contacter\b/iu',
            '/\bvous pouvez m(?:’|\')appeler\b/iu',
            '/\bvous pouvez me rappeler\b/iu',
            '/\bvous pouvez me joindre\b/iu',
            '/\bquand pouvez-vous m(?:’|\')appeler\b/iu',
            '/\bje suis disponible\b/iu',
            '/\bavec plaisir\b/iu',
            '/\bpourquoi pas\b/iu',
            '/\bje veux bien en savoir plus\b/iu',
        ];

        return $this->matches(
            $content,
            $patterns
        );
    }

    private function isNegative(string $content): bool
    {
        $patterns = [
            '/\bje ne suis pas intéressé(?:e)?\b/iu',
            '/\bnous ne sommes pas intéressés\b/iu',
            '/\bça ne m(?:’|\')intéresse pas\b/iu',
            '/\bcela ne m(?:’|\')intéresse pas\b/iu',
            '/\bpas intéressé(?:e)?\b/iu',
            '/\bnon merci\b/iu',
            '/\bpas besoin\b/iu',
            '/\bnous avons déjà (?:un|une)\b/iu',
            '/\bnous avons déjà ce qu(?:’|\')il faut\b/iu',
            '/\bce n(?:’|\')est pas nécessaire\b/iu',
            '/\bje décline\b/iu',
            '/\bnous déclinons\b/iu',
        ];

        return $this->matches(
            $content,
            $patterns
        );
    }

    private function isQuestion(string $content): bool
    {
        if (str_contains($content, '?')) {
            return true;
        }

        $patterns = [
            '/\bcombien\b/iu',
            '/\bquel(?:le|s)? tarif\b/iu',
            '/\bquel(?:le|s)? prix\b/iu',
            '/\bquel(?:le|s)? délai\b/iu',
            '/\bquel(?:le|s)? prestation\b/iu',
            '/\bcomment ça fonctionne\b/iu',
            '/\bcomment cela fonctionne\b/iu',
            '/\bpouvez-vous m(?:’|\')expliquer\b/iu',
            '/\bpouvez-vous préciser\b/iu',
            '/\bavez-vous des exemples\b/iu',
        ];

        return $this->matches(
            $content,
            $patterns
        );
    }

    /**
     * @param array<int, string> $patterns
     */
    private function matches(
        string $content,
        array $patterns
    ): bool {
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

    private function normalize(string $content): string
    {
        $content = trim($content);

        if ($content === '') {
            return '';
        }

        return preg_replace(
            '/[ \t]+/u',
            ' ',
            $content
        ) ?? $content;
    }
}
