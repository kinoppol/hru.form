<?php
declare(strict_types=1);

return [
    'up' => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE question_options (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                question_id INT UNSIGNED NOT NULL,
                label VARCHAR(500) NOT NULL,
                is_correct TINYINT(1) NOT NULL DEFAULT 0,
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                KEY question_options_question_idx (question_id),
                CONSTRAINT question_options_question_fk FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    },
    'down' => function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS question_options');
    },
];
