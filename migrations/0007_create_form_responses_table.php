<?php
declare(strict_types=1);

return [
    'up' => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE form_responses (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                form_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NULL,
                personal_data_json TEXT NULL,
                score INT NULL,
                max_score INT NULL,
                submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY form_responses_form_idx (form_id),
                CONSTRAINT form_responses_form_fk FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE,
                CONSTRAINT form_responses_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    },
    'down' => function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS form_responses');
    },
];
