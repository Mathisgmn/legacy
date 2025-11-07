<?php

class UserStatus
{
    private const ONLINE_TIMEOUT_SECONDS = 90;
    private ?PDO $conn = null;

    public function __construct()
    {
        try {
            $this->conn = Database::getInstance()->getConnection();
        } catch (Exception $e) {
            logWithDate('DB connection failed', $e->getMessage());
        }
    }

    public function setOnline(int $userId): void
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'INSERT INTO user_status (user_id, is_online, is_in_game, current_game_id, updated_at) ';
            $query .= 'VALUES (:user_id, 1, 0, NULL, NOW()) ';
            $query .= 'ON DUPLICATE KEY UPDATE is_online = 1, updated_at = NOW()';

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());
        }
    }

    public function setOffline(int $userId): void
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'UPDATE user_status SET is_online = 0, is_in_game = 0, current_game_id = NULL, updated_at = NOW() WHERE user_id = :user_id';

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());
        }
    }

    public function setInGame(int $userId, int $gameId): void
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'INSERT INTO user_status (user_id, is_online, is_in_game, current_game_id, updated_at) ';
            $query .= 'VALUES (:user_id, 1, 1, :game_id, NOW()) ';
            $query .= 'ON DUPLICATE KEY UPDATE is_online = 1, is_in_game = 1, current_game_id = VALUES(current_game_id), updated_at = NOW()';

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());
        }
    }

    public function clearGame(int $userId): void
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'UPDATE user_status SET is_in_game = 0, current_game_id = NULL, updated_at = NOW() WHERE user_id = :user_id';

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());
        }
    }

    public function listAvailableOpponents(int $excludeUserId): array
    {
        try {
            throwDbNullConnection($this->conn);

            $threshold = date('Y-m-d H:i:s', time() - self::ONLINE_TIMEOUT_SECONDS);

            $query = 'SELECT u.id, u.pseudo, u.avatar, ';
            $query .= 'CASE WHEN EXISTS (';
            $query .= '    SELECT 1 FROM game g ';
            $query .= '    WHERE g.status = :active_status ';
            $query .= '      AND (g.player1_id = u.id OR g.player2_id = u.id)';
            $query .= ') THEN 1 ELSE COALESCE(us.is_in_game, 0) END AS is_in_game ';
            $query .= 'FROM user u ';
            $query .= 'LEFT JOIN user_status us ON us.user_id = u.id ';
            $query .= 'WHERE u.id <> :user_id ';
            $query .= 'AND (';
            $query .= '    (us.user_id IS NOT NULL AND us.is_online = 1 AND us.updated_at >= :online_threshold) ';
            $query .= '    OR (us.user_id IS NULL AND u.last_connected_at IS NOT NULL AND u.last_connected_at >= :activity_threshold)';
            $query .= ') ';
            $query .= 'ORDER BY u.pseudo ASC';

            $stmt = $this->conn->prepare($query);
            $activeStatus = 'in_progress';
            $stmt->bindParam(':active_status', $activeStatus, PDO::PARAM_STR);
            $stmt->bindParam(':user_id', $excludeUserId, PDO::PARAM_INT);
            $stmt->bindParam(':online_threshold', $threshold, PDO::PARAM_STR);
            $stmt->bindParam(':activity_threshold', $threshold, PDO::PARAM_STR);
            $stmt->execute();

            $players = $stmt->fetchAll();
            return array_map(static function (array $player): array {
                $player['id'] = (int)$player['id'];
                $player['is_in_game'] = (int)$player['is_in_game'];
                return $player;
            }, $players);
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());
            return [];
        }
    }

    public function isUserAvailable(int $userId): bool
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'SELECT is_online, is_in_game FROM user_status WHERE user_id = :user_id';

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch();
            if (!$result) {
                return false;
            }

            return (int)$result['is_online'] === 1 && (int)$result['is_in_game'] === 0;
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());
            return false;
        }
    }
}