<?php
declare(strict_types=1);

return [
    'up' => function (PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE response_answers (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                response_id INT UNSIGNED NOT NULL,
                question_id INT UNSIGNED NOT NULL,
                answer_json TEXT NULL,
                is_correct TINYINT(1) NULL,
                KEY response_answers_response_idx (response_id),
                KEY response_answers_question_idx (question_id),
                CONSTRAINT response_answers_response_fk FOREIGN KEY (response_id) REFERENCES form_responses(id) ON DELETE CASCADE,
                CONSTRAINT response_answers_question_fk FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    },
    'down' => function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS response_answers');
    },
];
