<?php

declare(strict_types=1);

final class ProspectingLogger
{
    private const LEVELS = [
        'debug',
        'info',
        'warning',
        'error',
        'critical',
    ];

    private readonly string $runId;

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $channel,
        ?string $runId = null
    ) {
        $channel = trim($this->channel);

        if ($channel === '') {
            throw new InvalidArgumentException(
                'Le canal de log est obligatoire.'
            );
        }

        if (mb_strlen($channel) > 50) {
            throw new InvalidArgumentException(
                'Le canal de log est trop long.'
            );
        }

        $this->runId = $runId !== null
            ? $this->validateRunId($runId)
            : $this->generateRunId($channel);
    }

    public function getRunId(): string
    {
        return $this->runId;
    }

    public function debug(
        string $eventType,
        string $message,
        array $context = [],
        ?int $prospectId = null,
        ?int $emailId = null
    ): bool {
        return $this->log(
            'debug',
            $eventType,
            $message,
            $context,
            $prospectId,
            $emailId
        );
    }

    public function info(
        string $eventType,
        string $message,
        array $context = [],
        ?int $prospectId = null,
        ?int $emailId = null
    ): bool {
        return $this->log(
            'info',
            $eventType,
            $message,
            $context,
            $prospectId,
            $emailId
        );
    }

    public function warning(
        string $eventType,
        string $message,
        array $context = [],
        ?int $prospectId = null,
        ?int $emailId = null
    ): bool {
        return $this->log(
            'warning',
            $eventType,
            $message,
            $context,
            $prospectId,
            $emailId
        );
    }

    public function error(
        string $eventType,
        string $message,
        array $context = [],
        ?int $prospectId = null,
        ?int $emailId = null
    ): bool {
        return $this->log(
            'error',
            $eventType,
            $message,
            $context,
            $prospectId,
            $emailId
        );
    }

    public function critical(
        string $eventType,
        string $message,
        array $context = [],
        ?int $prospectId = null,
        ?int $emailId = null
    ): bool {
        return $this->log(
            'critical',
            $eventType,
            $message,
            $context,
            $prospectId,
            $emailId
        );
    }

    public function log(
        string $level,
        string $eventType,
        string $message,
        array $context = [],
        ?int $prospectId = null,
        ?int $emailId = null
    ): bool {
        $level = strtolower(trim($level));
        $eventType = trim($eventType);
        $message = trim($message);

        if (!in_array($level, self::LEVELS, true)) {
            throw new InvalidArgumentException(
                'Niveau de log invalide.'
            );
        }

        if ($eventType === '') {
            throw new InvalidArgumentException(
                'Le type d evenement est obligatoire.'
            );
        }

        if (mb_strlen($eventType) > 100) {
            throw new InvalidArgumentException(
                'Le type d evenement est trop long.'
            );
        }

        if ($message === '') {
            throw new InvalidArgumentException(
                'Le message de log est obligatoire.'
            );
        }

        if (mb_strlen($message) > 1000) {
            throw new InvalidArgumentException(
                'Le message de log est trop long.'
            );
        }

        if ($prospectId !== null && $prospectId <= 0) {
            throw new InvalidArgumentException(
                'ID prospect invalide.'
            );
        }

        if ($emailId !== null && $emailId <= 0) {
            throw new InvalidArgumentException(
                'ID email invalide.'
            );
        }

        try {
            $encodedContext = $context === []
                ? null
                : json_encode(
                    $context,
                    JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                );

            $statement = $this->pdo->prepare(
                '
                INSERT INTO prospecting_logs (
                    run_id,
                    channel,
                    level,
                    event_type,
                    message,
                    prospect_id,
                    email_id,
                    context
                ) VALUES (
                    :run_id,
                    :channel,
                    :level,
                    :event_type,
                    :message,
                    :prospect_id,
                    :email_id,
                    :context
                )
                '
            );

            return $statement->execute([
                'run_id' =>
                    $this->runId,

                'channel' =>
                    trim($this->channel),

                'level' =>
                    $level,

                'event_type' =>
                    $eventType,

                'message' =>
                    $message,

                'prospect_id' =>
                    $prospectId,

                'email_id' =>
                    $emailId,

                'context' =>
                    $encodedContext,
            ]);
        } catch (Throwable $exception) {
            error_log(
                '[PROSPECTION LOGGER] '
                . $exception->getMessage()
            );

            return false;
        }
    }

    private function generateRunId(
        string $channel
    ): string {
        return sprintf(
            '%s-%s-%s',
            preg_replace(
                '/[^a-z0-9_-]+/i',
                '-',
                strtolower($channel)
            ),
            date('YmdHis'),
            bin2hex(random_bytes(6))
        );
    }

    private function validateRunId(
        string $runId
    ): string {
        $runId = trim($runId);

        if ($runId === '') {
            throw new InvalidArgumentException(
                'Le run_id ne peut pas etre vide.'
            );
        }

        if (mb_strlen($runId) > 100) {
            throw new InvalidArgumentException(
                'Le run_id est trop long.'
            );
        }

        return $runId;
    }
}
