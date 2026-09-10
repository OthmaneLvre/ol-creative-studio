<?php

declare(strict_types=1);

interface ReplyGeneratorInterface
{
    public function generate(
        array $prospect,
        array $reply,
        array $outboundEmail
    ): array;
}
