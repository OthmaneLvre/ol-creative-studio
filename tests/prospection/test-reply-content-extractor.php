<?php

declare(strict_types=1);

require __DIR__
    . '/../../php/prospection/ReplyContentExtractor.php';

$extractor = new ReplyContentExtractor();

$tests = [
    [
        'name' => 'STOP simple',
        'body' => "STOP",
        'expected' => "STOP",
    ],
    [
        'name' => 'Réponse normale avec historique Gmail',
        'body' => "Bonjour,\nJe suis intéressé.\n\nLe 2 sept. 2026 à 20:20, OL Creative Studio a écrit :\n> Bonjour\n> Pour ne plus recevoir nos messages, répondez STOP.",
        'expected' => "Bonjour,\nJe suis intéressé.",
    ],
    [
        'name' => 'STOP avant historique',
        'body' => "STOP\n\nLe 2 sept. 2026 à 20:20, OL Creative Studio a écrit :\n> Bonjour",
        'expected' => "STOP",
    ],
    [
        'name' => 'STOP uniquement dans historique',
        'body' => "Merci pour votre message.\n\nOn Wed, Sep 2, 2026 at 8:20 PM OL Creative Studio wrote:\n> Pour ne plus recevoir nos messages, répondez STOP.",
        'expected' => "Merci pour votre message.",
    ],
];

$failed = 0;

foreach ($tests as $test) {
    $actual = $extractor->extract([
        'body_text' => $test['body'],
    ]);

    $passed = $actual === $test['expected'];

    echo sprintf(
        "[%s] %s\n",
        $passed ? 'OK' : 'FAIL',
        $test['name']
    );

    if (!$passed) {
        $failed++;

        echo "Attendu :\n";
        var_dump($test['expected']);

        echo "Obtenu :\n";
        var_dump($actual);
    }
}

echo "\n";

if ($failed > 0) {
    echo "ECHEC : {$failed} test(s).\n";
    exit(1);
}

echo "Tous les tests sont OK.\n";
