<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../php/prospection/HumanReplyClassifier.php';

$classifier =
    new HumanReplyClassifier();

$cases = [
    [
        'label' => 'Intérêt direct',
        'content' =>
            'Bonjour, je suis intéressé pour en discuter.',
        'expected' => 'positive',
    ],
    [
        'label' => 'Demande appel',
        'content' =>
            'Vous pouvez me contacter demain après-midi.',
        'expected' => 'positive',
    ],
    [
        'label' => 'Refus direct',
        'content' =>
            'Bonjour, je ne suis pas intéressé. Merci.',
        'expected' => 'negative',
    ],
    [
        'label' => 'Non merci',
        'content' =>
            'Non merci, nous avons déjà ce qu’il faut.',
        'expected' => 'negative',
    ],
    [
        'label' => 'Question prix',
        'content' =>
            'Bonjour, combien coûte un site vitrine ?',
        'expected' => 'question',
    ],
    [
        'label' => 'Question délai',
        'content' =>
            'Quel délai faut-il prévoir pour le projet ?',
        'expected' => 'question',
    ],
    [
        'label' => 'Réponse neutre',
        'content' =>
            'Bonjour, merci pour votre message.',
        'expected' => 'unknown',
    ],
    [
        'label' => 'Texte vide',
        'content' => '',
        'expected' => 'unknown',
    ],

    [
    'label' => 'Intérêt avec question prix',
    'content' =>
        'Je suis intéressé, combien ça coûte ?',
    'expected' => 'positive',
    ],
    [
        'label' => 'Refus temporaire',
        'content' =>
            'Je ne suis pas intéressé pour le moment.',
        'expected' => 'negative',
    ],
    [
        'label' => 'Demande rappel interrogative',
        'content' =>
            'Vous pouvez me rappeler demain ?',
        'expected' => 'positive',
    ],
    [
        'label' => 'Question seule',
        'content' =>
            'Est-ce que vous travaillez aussi avec WordPress ?',
        'expected' => 'question',
    ],
    [
        'label' => 'Réponse polie neutre',
        'content' =>
            'Merci pour ces informations, bonne journée.',
        'expected' => 'unknown',
    ],
];

$hasError = false;

foreach ($cases as $case) {
    $result =
        $classifier->classify(
            $case['content']
        );

    $ok =
        $result === $case['expected'];

    echo sprintf(
        '[%s] %-30s attendu=%s obtenu=%s',
        $ok ? 'OK' : 'KO',
        $case['label'],
        $case['expected'],
        $result
    );

    echo PHP_EOL;

    if (!$ok) {
        $hasError = true;
    }
}

if ($hasError) {
    echo PHP_EOL;
    echo 'Au moins un test a échoué.'
        . PHP_EOL;

    exit(1);
}

echo PHP_EOL;
echo 'Tous les tests sont OK.'
    . PHP_EOL;
