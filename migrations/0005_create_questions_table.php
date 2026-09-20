<?php
declare(strict_types=1);

return [
    'up' => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE questions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                form_id INT UNSIGNED NOT NULL,
                type ENUM('mc','checkbox','dropdown','scale','short') NOT NULL,
                text TEXT NOT NULL,
                scored TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY questions_form_idx (form_id),
                CONSTRAINT questions_form_fk FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    },
    'down' => function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS questions');
    },
];
