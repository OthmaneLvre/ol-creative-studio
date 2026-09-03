<?php

declare(strict_types=1);

require __DIR__
    . '/../../php/prospection/UnsubscribeManager.php';

$pdo = new PDO(
    'sqlite::memory:'
);

$manager = new UnsubscribeManager($pdo);

$tests = [
    ['STOP', true],
    ['stop', true],
    ['STOP.', true],
    ['Désinscrivez-moi', true],
    ['Desinscrivez-moi', true],
    ['Merci de ne plus me contacter.', true],
    ['Ne me contactez plus', true],
    ['Je souhaite me désinscrire', true],
    ['Je ne souhaite plus recevoir vos e-mails.', true],

    ['Bonjour, je suis intéressé.', false],
    ['Merci pour votre message.', false],
    ['Pouvez-vous me rappeler ?', false],
    ['Votre footer indique de répondre STOP.', false],
    ['Je ne souhaite plus attendre votre réponse.', false],
    ['Le mot stopping ne doit rien déclencher.', false],
];

$failed = 0;

foreach ($tests as [$content, $expected]) {
    $actual = $manager->isUnsubscribeRequest(
        $content
    );

    $passed = $actual === $expected;

    echo sprintf(
        "[%s] %-55s attendu=%s obtenu=%s\n",
        $passed ? 'OK' : 'FAIL',
        $content,
        $expected ? 'true' : 'false',
        $actual ? 'true' : 'false'
    );

    if (!$passed) {
        $failed++;
    }
}

echo "\n";

if ($failed > 0) {
    echo "ECHEC : {$failed} test(s).\n";
    exit(1);
}

echo "Tous les tests sont OK.\n";
