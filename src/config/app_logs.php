<?php

declare(strict_types=1);

function ensure_app_logs_table(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS app_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    username VARCHAR(80) NULL,
    action VARCHAR(60) NOT NULL,
    entity_type VARCHAR(60) NULL,
    entity_id INT NULL,
    description TEXT NULL,
    meta LONGTEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_app_logs_user_id (user_id),
    INDEX idx_app_logs_action (action),
    INDEX idx_app_logs_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
}

function app_log(
    PDO $pdo,
    ?int $userId,
    ?string $username,
    string $action,
    ?string $entityType = null,
    ?int $entityId = null,
    ?string $description = null,
    ?array $meta = null
): void {
    try {
        ensure_app_logs_table($pdo);

        $stmt = $pdo->prepare(<<<'SQL'
INSERT INTO app_logs
(user_id, username, action, entity_type, entity_id, description, meta, ip_address, user_agent)
VALUES
(:user_id, :username, :action, :entity_type, :entity_id, :description, :meta, :ip_address, :user_agent)
SQL);

        $stmt->execute([
            ':user_id' => $userId,
            ':username' => $username,
            ':action' => $action,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':description' => $description,
            ':meta' => $meta === null ? null : json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255, 'UTF-8') : null,
        ]);
    } catch (Throwable $e) {
        // El log no debe romper el login ni el uso de la app.
    }
}
