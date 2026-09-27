<?php
declare(strict_types=1);

return [
    'up' => function (PDO $pdo): void {
        $pdo->exec("ALTER TABLE forms ADD COLUMN show_answers TINYINT(1) NOT NULL DEFAULT 0 AFTER show_score");
    },
    'down' => function (PDO $pdo): void {
        $pdo->exec("ALTER TABLE forms DROP COLUMN show_answers");
    },
];
