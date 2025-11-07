<?php

class UserPresence
{
    public const STATUS_OFFLINE = 'offline';
    public const STATUS_ONLINE = 'online';
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_IN_GAME = 'in_game';

    private ?PDO $conn = null;

    public function __construct()
    {
        try {
            $this->conn = Database::getInstance()->getConnection();
            $this->initializeTable();
        } catch (Exception $e) {
            logWithDate('Presence storage init failed', $e->getMessage());
        }
    }

    private function initializeTable(): void
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'CREATE TABLE IF NOT EXISTS user_presence ('
                . 'user_id INT NOT NULL PRIMARY KEY,'
                . "status ENUM('offline','online','available','in_game') NOT NULL DEFAULT 'offline',"
                . 'last_connected_at DATETIME NULL,'
                . 'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,'
                . 'CONSTRAINT fk_user_presence_user FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE'
                . ') ENGINE=InnoDB';

            $this->conn->exec($query);
        } catch (PDOException|Exception $e) {
            logWithDate('Presence table init failed', $e->getMessage());
        }
    }

    public function updateStatus(int $userId, string $status): void
    {
        if (!$this->isValidStatus($status)) {
            logWithDate('Presence invalid status', (string) $status);
            return;
        }

        try {
            throwDbNullConnection($this->conn);

            $query = 'INSERT INTO user_presence (user_id, status, last_connected_at) '
                . 'VALUES (:user_id, :status, UTC_TIMESTAMP()) '
                . 'ON DUPLICATE KEY UPDATE status = VALUES(status), last_connected_at = UTC_TIMESTAMP()';

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':status', $status);
            $stmt->execute();
        } catch (PDOException|Exception $e) {
            logWithDate('Presence status update failed', $e->getMessage());
        }
    }

    public function markOffline(int $userId): void
    {
        $this->updateStatus($userId, self::STATUS_OFFLINE);
    }

    public function touch(int $userId): void
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'UPDATE user_presence '
                . 'SET last_connected_at = UTC_TIMESTAMP(), '
                . "status = CASE WHEN status = 'offline' THEN 'available' ELSE status END "
                . 'WHERE user_id = :user_id';

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() === 0) {
                $this->updateStatus($userId, self::STATUS_AVAILABLE);
            }
        } catch (PDOException|Exception $e) {
            logWithDate('Presence touch failed', $e->getMessage());
        }
    }

    public function expireInactive(int $timeoutSeconds, ?string $threshold = null): void
    {
        try {
            throwDbNullConnection($this->conn);

            $seconds = $this->normalizeTimeout($timeoutSeconds);
            $thresholdDate = $threshold ?? $this->computeThresholdDate($seconds);

            $offline = self::STATUS_OFFLINE;

            if ($threshold === null) {
                $query = 'UPDATE user_presence '
                    . 'SET status = :offline '
                    . 'WHERE status <> :offline '
                    . 'AND (last_connected_at IS NULL '
                    . 'OR last_connected_at < (UTC_TIMESTAMP() - INTERVAL ' . $seconds . ' SECOND))';

                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':offline', $offline);
            } else {
                $query = 'UPDATE user_presence '
                    . 'SET status = :offline '
                    . 'WHERE status <> :offline '
                    . 'AND (last_connected_at IS NULL OR last_connected_at < :threshold)';

                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':offline', $offline);
                $stmt->bindParam(':threshold', $thresholdDate);
            }

            $stmt->execute();
        } catch (PDOException|Exception $e) {
            logWithDate('Presence expiration failed', $e->getMessage());
        }
    }

    public function getAvailableUsers(?int $excludeUserId = null, int $timeoutSeconds = 300): array
    {
        try {
            throwDbNullConnection($this->conn);

            $seconds = $this->normalizeTimeout($timeoutSeconds);
            $this->expireInactive($seconds);

            $query = 'SELECT u.id, u.pseudo, up.status, up.last_connected_at '
                . 'FROM user_presence up '
                . 'JOIN user u ON u.id = up.user_id '
                . "WHERE up.status IN ('online', 'available') "
                . 'AND up.last_connected_at IS NOT NULL '
                . 'AND up.last_connected_at >= (UTC_TIMESTAMP() - INTERVAL ' . $seconds . ' SECOND)';

            if ($excludeUserId !== null) {
                $query .= ' AND up.user_id <> :exclude_user_id';
            }

            $stmt = $this->conn->prepare($query);

            if ($excludeUserId !== null) {
                $stmt->bindParam(':exclude_user_id', $excludeUserId, PDO::PARAM_INT);
            }

            $stmt->execute();

            return $stmt->fetchAll() ?: [];
        } catch (PDOException|Exception $e) {
            logWithDate('Presence fetch failed', $e->getMessage());
            return [];
        }
    }

    private function computeThresholdDate(int $timeoutSeconds): string
    {
        $seconds = $this->normalizeTimeout($timeoutSeconds);

        return gmdate('Y-m-d H:i:s', time() - $seconds);
    }

    private function normalizeTimeout(int $timeoutSeconds): int
    {
        return max((int) $timeoutSeconds, 1);
    }

    private function isValidStatus(string $status): bool
    {
        return in_array($status, [
            self::STATUS_OFFLINE,
            self::STATUS_ONLINE,
            self::STATUS_AVAILABLE,
            self::STATUS_IN_GAME,
        ], true);
    }
}
