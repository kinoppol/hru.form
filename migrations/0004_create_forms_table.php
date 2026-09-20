<?php
declare(strict_types=1);

return [
    'up' => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE forms (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                status ENUM('draft','published') NOT NULL DEFAULT 'draft',
                shuffle TINYINT(1) NOT NULL DEFAULT 0,
                show_score TINYINT(1) NOT NULL DEFAULT 1,
                require_login TINYINT(1) NOT NULL DEFAULT 0,
                theme_color VARCHAR(7) NOT NULL DEFAULT '#9184d9',
                share_token VARCHAR(32) NOT NULL UNIQUE,
                personal_fields_json TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY forms_user_idx (user_id),
                CONSTRAINT forms_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    },
    'down' => function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS forms');
    },
];
