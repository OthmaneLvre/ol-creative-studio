<?php

declare(strict_types=1);

final class InboxMonitor
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchRecentMessages(
        int $limit = 20
    ): array {
        if (!extension_loaded('imap')) {
            throw new RuntimeException(
                'Extension IMAP non chargée.'
            );
        }

        if ($limit <= 0) {
            throw new InvalidArgumentException(
                'La limite doit être supérieure à zéro.'
            );
        }

        $connection = $this->openReadOnlyConnection();

        try {
            $messageCount = imap_num_msg($connection);

            if ($messageCount <= 0) {
                return [];
            }

            $firstMessage = max(
                1,
                $messageCount - $limit + 1
            );

            $messages = [];

            for (
                $messageNumber = $messageCount;
                $messageNumber >= $firstMessage;
                $messageNumber--
            ) {
                $message = $this->extractMessage(
                    $connection,
                    $messageNumber
                );

                if ($message !== null) {
                    $messages[] = $message;
                }
            }

            return $messages;
        } finally {
            imap_close($connection);
        }
    }

    /**
     * @return resource|\IMAP\Connection
     */
    private function openReadOnlyConnection()
    {
        $host = trim(
            (string) ($this->config['host'] ?? '')
        );

        $port = (int) (
            $this->config['port'] ?? 993
        );

        $username = trim(
            (string) ($this->config['username'] ?? '')
        );

        $password = (string) (
            $this->config['password'] ?? ''
        );

        $mailboxName = trim(
            (string) ($this->config['mailbox'] ?? 'INBOX')
        );

        $encryption = trim(
            strtolower(
                (string) (
                    $this->config['encryption']
                    ?? 'ssl'
                )
            )
        );

        if (
            $host === ''
            || $username === ''
            || $password === ''
            || $mailboxName === ''
        ) {
            throw new RuntimeException(
                'Configuration IMAP incomplète.'
            );
        }

        $flags = '/imap';

        if ($encryption === 'ssl') {
            $flags .= '/ssl';
        } elseif ($encryption === 'tls') {
            $flags .= '/tls';
        }

        $mailbox = sprintf(
            '{%s:%d%s}%s',
            $host,
            $port,
            $flags,
            $mailboxName
        );

        imap_errors();
        imap_alerts();

        $connection = @imap_open(
            $mailbox,
            $username,
            $password,
            OP_READONLY,
            1
        );

        if ($connection === false) {
            $errors = imap_errors();

            throw new RuntimeException(
                'Connexion IMAP impossible : '
                . implode(
                    ' | ',
                    $errors ?: ['erreur inconnue']
                )
            );
        }

        return $connection;
    }

    /**
     * @param resource|\IMAP\Connection $connection
     *
     * @return array<string, mixed>|null
     */
    private function extractMessage(
        $connection,
        int $messageNumber
    ): ?array {
        $header = imap_headerinfo(
            $connection,
            $messageNumber
        );

        if ($header === false) {
            return null;
        }

        $uid = imap_uid(
            $connection,
            $messageNumber
        );

        $senderEmail = $this->extractSenderEmail(
            $header
        );

        $subject = isset($header->subject)
            ? $this->decodeHeader(
                (string) $header->subject
            )
            : null;

        $messageId = isset($header->message_id)
            ? trim((string) $header->message_id)
            : null;

        $inReplyTo = isset($header->in_reply_to)
            ? trim((string) $header->in_reply_to)
            : null;

        $references = $this->extractReferences(
            $connection,
            $messageNumber
        );

        $bodies = $this->extractBodies(
            $connection,
            $messageNumber
        );

        $receivedAt = null;

        if (isset($header->udate)) {
            $receivedAt = (new DateTimeImmutable())
                ->setTimestamp(
                    (int) $header->udate
                )
                ->format('Y-m-d H:i:s');
        }

        return [
            'message_number' => $messageNumber,
            'uid' => $uid !== false
                ? (int) $uid
                : null,

            'sender_email' => $senderEmail,
            'subject' => $subject,

            'message_id' => $messageId,
            'in_reply_to' => $inReplyTo,
            'references' => $references,

            'body_text' => $bodies['text'],
            'body_html' => $bodies['html'],

            'received_at' => $receivedAt,
        ];

    }

    private function extractSenderEmail(
        object $header
    ): ?string {
        if (
            empty($header->from)
            || !is_array($header->from)
        ) {
            return null;
        }

        $from = $header->from[0] ?? null;

        if (!is_object($from)) {
            return null;
        }

        $mailbox = isset($from->mailbox)
            ? (string) $from->mailbox
            : '';

        $host = isset($from->host)
            ? (string) $from->host
            : '';

        if ($mailbox === '' || $host === '') {
            return null;
        }

        $email = strtolower(
            trim($mailbox . '@' . $host)
        );

        return filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        ) !== false
            ? $email
            : null;
    }

    private function decodeHeader(
        string $value
    ): string {
        $decodedParts = imap_mime_header_decode(
            $value
        );

        $decoded = '';

        foreach ($decodedParts as $part) {
            $charset = strtoupper(
                (string) (
                    $part->charset ?? 'DEFAULT'
                )
            );

            $text = (string) (
                $part->text ?? ''
            );

            if (
                $charset !== 'DEFAULT'
                && $charset !== 'UTF-8'
            ) {
                $converted = @mb_convert_encoding(
                    $text,
                    'UTF-8',
                    $charset
                );

                if ($converted !== false) {
                    $text = $converted;
                }
            }

            $decoded .= $text;
        }

        return trim($decoded);
    }

    /**
     * @param resource|\IMAP\Connection $connection
     *
     * @return array<int, string>
     */
    private function extractReferences(
        $connection,
        int $messageNumber
    ): array {
        $rawHeader = imap_fetchheader(
            $connection,
            $messageNumber
        );

        if ($rawHeader === false) {
            return [];
        }

        if (
            !preg_match(
                '/^References:\s*(.+(?:\r?\n[ \t].+)*)/mi',
                $rawHeader,
                $matches
            )
        ) {
            return [];
        }

        $referencesLine = preg_replace(
            '/\r?\n[ \t]+/',
            ' ',
            $matches[1]
        );

        if (!is_string($referencesLine)) {
            return [];
        }

        preg_match_all(
            '/<[^>]+>/',
            $referencesLine,
            $referenceMatches
        );

        return array_values(
            array_unique(
                $referenceMatches[0] ?? []
            )
        );
    }

    /**
 * @param resource|\IMAP\Connection $connection
 *
 * @return array{
 *     text: ?string,
 *     html: ?string
 * }
 */
private function extractBodies(
    $connection,
    int $messageNumber
): array {
    $structure = imap_fetchstructure(
        $connection,
        $messageNumber
    );

    if ($structure === false) {
        return [
            'text' => null,
            'html' => null,
        ];
    }

    $bodies = [
        'text' => null,
        'html' => null,
    ];

    $this->extractBodyParts(
        $connection,
        $messageNumber,
        $structure,
        '',
        $bodies
    );

    return $bodies;
}

/**
 * @param resource|\IMAP\Connection $connection
 * @param array{text: ?string, html: ?string} $bodies
 */
private function extractBodyParts(
    $connection,
    int $messageNumber,
    object $structure,
    string $partNumber,
    array &$bodies
): void {
    if (
        isset($structure->parts)
        && is_array($structure->parts)
        && $structure->parts !== []
    ) {
        foreach (
            $structure->parts
            as $index => $part
        ) {
            if (!is_object($part)) {
                continue;
            }

            $childPartNumber =
                $partNumber === ''
                    ? (string) ($index + 1)
                    : $partNumber
                        . '.'
                        . ($index + 1);

            $this->extractBodyParts(
                $connection,
                $messageNumber,
                $part,
                $childPartNumber,
                $bodies
            );
        }

        return;
    }

    if (!$this->isTextPart($structure)) {
        return;
    }

    $subtype = strtoupper(
        (string) (
            $structure->subtype ?? ''
        )
    );

    if (
        $subtype !== 'PLAIN'
        && $subtype !== 'HTML'
    ) {
        return;
    }

    $body = $this->fetchBodyPart(
        $connection,
        $messageNumber,
        $partNumber,
        $structure
    );

    if ($body === null) {
        return;
    }

    if (
        $subtype === 'PLAIN'
        && $bodies['text'] === null
    ) {
        $bodies['text'] = $body;

        return;
    }

    if (
        $subtype === 'HTML'
        && $bodies['html'] === null
    ) {
        $bodies['html'] = $body;
    }
}

/**
 * @param resource|\IMAP\Connection $connection
 */
private function fetchBodyPart(
    $connection,
    int $messageNumber,
    string $partNumber,
    object $structure
): ?string {
    if ($partNumber === '') {
        $body = imap_body(
            $connection,
            $messageNumber,
            FT_PEEK
        );
    } else {
        $body = imap_fetchbody(
            $connection,
            $messageNumber,
            $partNumber,
            FT_PEEK
        );
    }

    if ($body === false) {
        return null;
    }

    $body = $this->decodeTransferEncoding(
        $body,
        (int) ($structure->encoding ?? 0)
    );

    $charset = $this->extractCharset(
        $structure
    );

    $body = $this->convertToUtf8(
        $body,
        $charset
    );

    $body = trim($body);

    return $body !== ''
        ? $body
        : null;
}

private function isTextPart(
    object $structure
): bool {
    /*
     * IMAP type 0 = text.
     */
    return (int) (
        $structure->type ?? -1
    ) === 0;
}

private function decodeTransferEncoding(
    string $body,
    int $encoding
): string {
    /*
     * IMAP encodings:
     *
     * 0 = 7BIT
     * 1 = 8BIT
     * 2 = BINARY
     * 3 = BASE64
     * 4 = QUOTED-PRINTABLE
     * 5 = OTHER
     */
    if ($encoding === 3) {
        $decoded = base64_decode(
            $body,
            true
        );

        return $decoded !== false
            ? $decoded
            : $body;
    }

    if ($encoding === 4) {
        return quoted_printable_decode(
            $body
        );
    }

    return $body;
}

private function extractCharset(
    object $structure
): ?string {
    $parameterGroups = [];

    if (
        isset($structure->parameters)
        && is_array($structure->parameters)
    ) {
        $parameterGroups[] =
            $structure->parameters;
    }

    if (
        isset($structure->dparameters)
        && is_array($structure->dparameters)
    ) {
        $parameterGroups[] =
            $structure->dparameters;
    }

    foreach (
        $parameterGroups
        as $parameters
    ) {
        foreach ($parameters as $parameter) {
            if (!is_object($parameter)) {
                continue;
            }

            $attribute = strtoupper(
                trim(
                    (string) (
                        $parameter->attribute
                        ?? ''
                    )
                )
            );

            if ($attribute !== 'CHARSET') {
                continue;
            }

            $charset = trim(
                (string) (
                    $parameter->value
                    ?? ''
                )
            );

            return $charset !== ''
                ? $charset
                : null;
        }
    }

    return null;
}

private function convertToUtf8(
    string $body,
    ?string $charset
): string {
    if ($charset === null) {
        return $body;
    }

    $normalizedCharset = strtoupper(
        trim($charset, " \t\n\r\0\x0B\"'")
    );

    if (
        $normalizedCharset === ''
        || $normalizedCharset === 'UTF-8'
        || $normalizedCharset === 'UTF8'
        || $normalizedCharset === 'US-ASCII'
        || $normalizedCharset === 'ASCII'
    ) {
        return $body;
    }

    if (!function_exists('mb_convert_encoding')) {
        return $body;
    }

    try {
        return mb_convert_encoding(
            $body,
            'UTF-8',
            $normalizedCharset
        );
    } catch (ValueError) {
        return $body;
    }
}
}
