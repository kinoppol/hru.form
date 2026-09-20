<?php
declare(strict_types=1);

return [
    'up' => function (PDO $pdo): void {
        $pdo->exec("
            ALTER TABLE users
            ADD COLUMN admin_id INT UNSIGNED NULL UNIQUE AFTER id,
            ADD CONSTRAINT users_admin_fk FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
        ");
    },
    'down' => function (PDO $pdo): void {
        $pdo->exec('ALTER TABLE users DROP FOREIGN KEY users_admin_fk, DROP COLUMN admin_id');
    },
];
