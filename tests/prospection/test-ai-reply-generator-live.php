<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../php/db.php';

require_once __DIR__
    . '/../../php/prospection/AiReplyGenerator.php';

echo "=== TEST LIVE AI REPLY GENERATOR ==="
    . PHP_EOL
    . PHP_EOL;

$apiKey =
    $_ENV['OPENAI_API_KEY']
    ?? getenv('OPENAI_API_KEY')
    ?: '';

if (trim($apiKey) === '') {
    fwrite(
        STDERR,
        "OPENAI_API_KEY absente."
        . PHP_EOL
    );

    exit(1);
}

$model =
    $_ENV['OPENAI_MODEL']
    ?? getenv('OPENAI_MODEL')
    ?: 'gpt-5.6-luna';

$generator =
    new AiReplyGenerator(
        $apiKey,
        $model
    );

$prospect = [
    'company_name' =>
        'Entreprise Test OL Creative Studio',

    'activity' =>
        'Plomberie',

    'city' =>
        'Céret',
];

$outboundEmail = [
    'subject' =>
        'Votre présence en ligne',

    'body_text' =>
        "Bonjour,\n\n"
        . "J’ai regardé votre présence en ligne "
        . "et je souhaitais vous proposer un "
        . "mini-audit gratuit de votre site.\n\n"
        . "Bonne journée.",
];

$cases = [
    [
        'name' =>
            'Réponse positive',

        'reply' => [
            'classification' =>
                'positive',

            'body_text' =>
                "Bonjour,\n\n"
                . "Merci pour votre message. "
                . "Je serais intéressé pour en discuter. "
                . "Vous pouvez me rappeler.\n",
        ],
    ],

    [
        'name' =>
            'Question tarif sans prix fourni',

        'reply' => [
            'classification' =>
                'question',

            'body_text' =>
                "Bonjour,\n\n"
                . "Combien coûte la création "
                . "d’un site vitrine ?\n",
        ],
    ],
];

foreach ($cases as $case) {
    echo "----------------------------------------"
        . PHP_EOL;

    echo $case['name']
        . PHP_EOL;

    echo "----------------------------------------"
        . PHP_EOL;

    try {
        $draft =
            $generator->generate(
                $prospect,
                $case['reply'],
                $outboundEmail
            );

        echo "OBJET : "
            . $draft['subject']
            . PHP_EOL
            . PHP_EOL;

        echo "CORPS :"
            . PHP_EOL;

        echo $draft['body']
            . PHP_EOL
            . PHP_EOL;
    } catch (Throwable $e) {
        echo "[ERREUR] "
            . get_class($e)
            . " : "
            . $e->getMessage()
            . PHP_EOL
            . PHP_EOL;
    }
}

echo "=== FIN DU TEST ==="
    . PHP_EOL;
    