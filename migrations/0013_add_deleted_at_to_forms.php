<?php
declare(strict_types=1);

return [
    'up' => function (PDO $pdo): void {
        $pdo->exec("ALTER TABLE forms ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL AFTER updated_at, ADD KEY forms_deleted_idx (user_id, deleted_at)");
    },
    'down' => function (PDO $pdo): void {
        $pdo->exec("ALTER TABLE forms DROP KEY forms_deleted_idx, DROP COLUMN deleted_at");
    },
];
