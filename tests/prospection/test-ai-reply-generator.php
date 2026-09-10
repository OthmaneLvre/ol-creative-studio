<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../php/prospection/AiReplyGenerator.php';

echo "=== TEST AI REPLY GENERATOR ==="
    . PHP_EOL
    . PHP_EOL;

$passed = 0;
$failed = 0;

function assertThrows(
    string $name,
    callable $callback,
    string $expectedMessagePart
): void {
    global $passed, $failed;

    try {
        $callback();

        echo "[FAIL] {$name}"
            . " - aucune exception"
            . PHP_EOL;

        $failed++;
    } catch (InvalidArgumentException $e) {
        if (
            str_contains(
                $e->getMessage(),
                $expectedMessagePart
            )
        ) {
            echo "[OK]   {$name}"
                . PHP_EOL;

            $passed++;
            return;
        }

        echo "[FAIL] {$name}"
            . " - message inattendu : "
            . $e->getMessage()
            . PHP_EOL;

        $failed++;
    } catch (Throwable $e) {
        echo "[FAIL] {$name}"
            . " - exception inattendue : "
            . get_class($e)
            . " - "
            . $e->getMessage()
            . PHP_EOL;

        $failed++;
    }
}


/*
 * ------------------------------------------------------------
 * 1. Clé API obligatoire
 * ------------------------------------------------------------
 */

assertThrows(
    'Clé API vide refusée',
    static function (): void {
        new AiReplyGenerator('');
    },
    'Clé API OpenAI absente'
);


/*
 * ------------------------------------------------------------
 * Générateur avec fausse clé.
 *
 * Aucun appel réseau ne sera effectué dans les tests suivants :
 * les données sont volontairement invalides avant request().
 * ------------------------------------------------------------
 */

$generator = new AiReplyGenerator(
    'test-api-key'
);

$prospect = [
    'company_name' => 'Entreprise Test',
    'activity' => 'Plomberie',
    'city' => 'Céret',
];

$outboundEmail = [
    'subject' => 'Votre présence en ligne',
    'body_text' =>
        'Bonjour, je vous contacte concernant votre site.',
];


/*
 * ------------------------------------------------------------
 * 2. Classifications interdites
 * ------------------------------------------------------------
 */

foreach (
    [
        'negative',
        'automatic',
        'unsubscribed',
        'unknown',
    ] as $classification
) {
    assertThrows(
        "Classification {$classification} refusée",
        static function () use (
            $generator,
            $prospect,
            $outboundEmail,
            $classification
        ): void {
            $generator->generate(
                $prospect,
                [
                    'classification' =>
                        $classification,

                    'body_text' =>
                        'Message de test.',
                ],
                $outboundEmail
            );
        },
        'positive ou question'
    );
}


/*
 * ------------------------------------------------------------
 * 3. Classification absente
 * ------------------------------------------------------------
 */

assertThrows(
    'Classification absente refusée',
    static function () use (
        $generator,
        $prospect,
        $outboundEmail
    ): void {
        $generator->generate(
            $prospect,
            [
                'body_text' =>
                    'Message de test.',
            ],
            $outboundEmail
        );
    },
    'positive ou question'
);


/*
 * ------------------------------------------------------------
 * 4. Corps vide
 * ------------------------------------------------------------
 */

assertThrows(
    'Réponse positive vide refusée',
    static function () use (
        $generator,
        $prospect,
        $outboundEmail
    ): void {
        $generator->generate(
            $prospect,
            [
                'classification' =>
                    'positive',

                'body_text' => '',
            ],
            $outboundEmail
        );
    },
    'Contenu de la réponse absent'
);


assertThrows(
    'Réponse question vide refusée',
    static function () use (
        $generator,
        $prospect,
        $outboundEmail
    ): void {
        $generator->generate(
            $prospect,
            [
                'classification' =>
                    'question',

                'body_text' =>
                    "   \n\t   ",
            ],
            $outboundEmail
        );
    },
    'Contenu de la réponse absent'
);


/*
 * ------------------------------------------------------------
 * Résultat
 * ------------------------------------------------------------
 */

echo PHP_EOL;
echo "Tests réussis : {$passed}"
    . PHP_EOL;

echo "Tests échoués : {$failed}"
    . PHP_EOL;

if ($failed > 0) {
    exit(1);
}

echo PHP_EOL
    . "AI Reply Generator guards : OK"
    . PHP_EOL;

exit(0);
