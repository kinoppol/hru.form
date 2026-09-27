<?php
declare(strict_types=1);

return [
    'up' => function (PDO $pdo): void {
        $pdo->exec("ALTER TABLE forms ADD COLUMN require_all_answers TINYINT(1) NOT NULL DEFAULT 1 AFTER show_answers");
    },
    'down' => function (PDO $pdo): void {
        $pdo->exec("ALTER TABLE forms DROP COLUMN require_all_answers");
    },
];
