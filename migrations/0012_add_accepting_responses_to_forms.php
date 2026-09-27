<?php
declare(strict_types=1);

return [
    'up' => function (PDO $pdo): void {
        $pdo->exec("ALTER TABLE forms ADD COLUMN accepting_responses TINYINT(1) NOT NULL DEFAULT 1 AFTER require_all_answers");
    },
    'down' => function (PDO $pdo): void {
        $pdo->exec("ALTER TABLE forms DROP COLUMN accepting_responses");
    },
];
