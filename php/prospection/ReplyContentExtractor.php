<?php

declare(strict_types=1);

final class ReplyContentExtractor
{
    public function extract(array $incomingMessage): string
    {
        $body = $incomingMessage['body_text'] ?? null;

        if (!is_string($body)) {
            return '';
        }

        $body = $this->normalizeLineEndings($body);
        $lines = explode("\n", $body);

        $newContent = [];

        foreach ($lines as $line) {
            if ($this->startsQuotedHistory($line)) {
                break;
            }

            $newContent[] = $line;
        }

        return trim(
            implode("\n", $newContent)
        );
    }

    private function normalizeLineEndings(
        string $body
    ): string {
        return str_replace(
            ["\r\n", "\r"],
            "\n",
            $body
        );
    }

    private function startsQuotedHistory(
        string $line
    ): bool {
        $trimmed = trim($line);

        if ($trimmed === '') {
            return false;
        }

        if (str_starts_with($trimmed, '>')) {
            return true;
        }

        $patterns = [
            '/^Le .+ a écrit\s*:$/iu',
            '/^On .+ wrote\s*:$/iu',
            '/^De\s*:\s*.+$/iu',
            '/^From\s*:\s*.+$/iu',
            '/^-{2,}\s*Message d[\'’]origine\s*-{2,}$/iu',
            '/^-{2,}\s*Original Message\s*-{2,}$/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $trimmed) === 1) {
                return true;
            }
        }

        return false;
    }
}
