<?php

require_once dirname(__DIR__) . '/Core/Database.php';
require_once dirname(__DIR__, 2) . '/helpers/global_helper.php';

class GameInvitation
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_STARTED = 'started';

    private ?PDO $conn = null;

    public function __construct()
    {
        try {
            $this->conn = Database::getInstance()->getConnection();
            $this->initializeTable();
        } catch (Exception $e) {
            logWithDate('Invitation storage init failed', $e->getMessage());
        }
    }

    private function initializeTable(): void
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'CREATE TABLE IF NOT EXISTS game_invitation ('
                . 'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,'
                . 'game_id INT NOT NULL,'
                . 'sender_id INT NOT NULL,'
                . 'recipient_id INT NOT NULL,'
                . "status ENUM('pending','cancelled','started') NOT NULL DEFAULT 'pending',"
                . 'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,'
                . 'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,'
                . 'started_at DATETIME NULL,'
                . 'UNIQUE KEY unique_invite (game_id, sender_id, recipient_id),'
                . 'CONSTRAINT fk_game_invitation_sender FOREIGN KEY (sender_id) REFERENCES user(id) ON DELETE CASCADE,'
                . 'CONSTRAINT fk_game_invitation_recipient FOREIGN KEY (recipient_id) REFERENCES user(id) ON DELETE CASCADE'
                . ') ENGINE=InnoDB';

            $this->conn->exec($query);
        } catch (PDOException|Exception $e) {
            logWithDate('Invitation table init failed', $e->getMessage());
        }
    }

    public function send(int $gameId, int $senderId, int $recipientId): ?array
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'INSERT INTO game_invitation (game_id, sender_id, recipient_id, status) '
                . 'VALUES (:game_id, :sender_id, :recipient_id, :status) '
                . 'ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = CURRENT_TIMESTAMP, started_at = NULL';

            $stmt = $this->conn->prepare($query);
            $status = self::STATUS_PENDING;
            $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
            $stmt->bindParam(':sender_id', $senderId, PDO::PARAM_INT);
            $stmt->bindParam(':recipient_id', $recipientId, PDO::PARAM_INT);
            $stmt->bindParam(':status', $status);
            $stmt->execute();

            return $this->findOne($gameId, $senderId, $recipientId);
        } catch (PDOException|Exception $e) {
            logWithDate('Invitation send failed', $e->getMessage());
            return null;
        }
    }

    public function cancel(int $gameId, int $senderId, int $recipientId): ?array
    {
        return $this->updateStatus($gameId, $senderId, $recipientId, self::STATUS_CANCELLED);
    }

    public function start(int $gameId, int $senderId, int $recipientId): ?array
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'UPDATE game_invitation SET status = :status, started_at = NOW() '
                . 'WHERE game_id = :game_id AND sender_id = :sender_id AND recipient_id = :recipient_id';

            $stmt = $this->conn->prepare($query);
            $status = self::STATUS_STARTED;
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
            $stmt->bindParam(':sender_id', $senderId, PDO::PARAM_INT);
            $stmt->bindParam(':recipient_id', $recipientId, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() === 0) {
                return null;
            }

            return $this->findOne($gameId, $senderId, $recipientId);
        } catch (PDOException|Exception $e) {
            logWithDate('Invitation start failed', $e->getMessage());
            return null;
        }
    }

    /**
     * @param int $recipientId
     * @param array<int, string> $statuses
     *
     * @return array<int, array<string, mixed>>
     */
    public function listForRecipient(int $recipientId, array $statuses = []): array
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'SELECT gi.*, '
                . 'sender.pseudo AS sender_pseudo, '
                . 'sender.email AS sender_email, '
                . 'sender.first_name AS sender_first_name, '
                . 'sender.last_name AS sender_last_name, '
                . 'game.status AS game_status '
                . 'FROM game_invitation gi '
                . 'INNER JOIN user sender ON sender.id = gi.sender_id '
                . 'LEFT JOIN game ON game.id = gi.game_id '
                . 'WHERE gi.recipient_id = :recipient_id';

            $bindings = [':recipient_id' => $recipientId];

            if ($statuses) {
                $placeholders = [];
                foreach ($statuses as $index => $status) {
                    $placeholder = ':status_' . $index;
                    $placeholders[] = $placeholder;
                    $bindings[$placeholder] = $status;
                }
                $query .= ' AND gi.status IN (' . implode(',', $placeholders) . ')';
            }

            $query .= ' ORDER BY gi.updated_at DESC';

            $stmt = $this->conn->prepare($query);
            foreach ($bindings as $placeholder => $value) {
                if ($placeholder === ':recipient_id') {
                    $stmt->bindValue($placeholder, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($placeholder, $value);
                }
            }

            $stmt->execute();

            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $result ?: [];
        } catch (PDOException|Exception $e) {
            logWithDate('Invitation list failed', $e->getMessage());
            return [];
        }
    }

    private function updateStatus(int $gameId, int $senderId, int $recipientId, string $status): ?array
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'UPDATE game_invitation SET status = :status WHERE game_id = :game_id '
                . 'AND sender_id = :sender_id AND recipient_id = :recipient_id';

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
            $stmt->bindParam(':sender_id', $senderId, PDO::PARAM_INT);
            $stmt->bindParam(':recipient_id', $recipientId, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() === 0) {
                return null;
            }

            return $this->findOne($gameId, $senderId, $recipientId);
        } catch (PDOException|Exception $e) {
            logWithDate('Invitation update failed', $e->getMessage());
            return null;
        }
    }

    private function findOne(int $gameId, int $senderId, int $recipientId): ?array
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'SELECT * FROM game_invitation WHERE game_id = :game_id '
                . 'AND sender_id = :sender_id AND recipient_id = :recipient_id';

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
            $stmt->bindParam(':sender_id', $senderId, PDO::PARAM_INT);
            $stmt->bindParam(':recipient_id', $recipientId, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch();

            return $result ?: null;
        } catch (PDOException|Exception $e) {
            logWithDate('Invitation fetch failed', $e->getMessage());
            return null;
        }
    }
}
