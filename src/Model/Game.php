<?php

require_once dirname(__DIR__) . '/Core/Database.php';
require_once dirname(__DIR__, 2) . '/helpers/global_helper.php';

class Game
{
    private ?PDO $conn = null;

    public function __construct()
    {
        try {
            $this->conn = Database::getInstance()->getConnection();
            $this->initializeSchema();
        } catch (Exception $e) {
            logWithDate('DB connection failed', $e->getMessage());
        }
    }

    private function initializeSchema(): void
    {
        try {
            throwDbNullConnection($this->conn);

            $createQuery = 'CREATE TABLE IF NOT EXISTS game ('
                . 'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,'
                . 'inviter_id INT NULL,'
                . 'invitee_id INT NULL,'
                . "target_word VARCHAR(6) NOT NULL," 
                . "status ENUM('pending','active','won','lost','timeout','cancelled') NOT NULL DEFAULT 'pending',"
                . 'max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 8,'
                . 'current_turn TINYINT UNSIGNED NOT NULL DEFAULT 1,'
                . 'winner_id INT NULL,'
                . 'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,'
                . 'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,'
                . 'started_at DATETIME NULL,'
                . 'last_guess_at DATETIME NULL,'
                . 'ended_at DATETIME NULL'
                . ') ENGINE=InnoDB';

            $this->conn->exec($createQuery);

            $columns = $this->getExistingColumns('game');

            $statements = [];

            if (!isset($columns['inviter_id'])) {
                $statements[] = 'ADD COLUMN inviter_id INT NULL AFTER id';
            }

            if (!isset($columns['invitee_id'])) {
                $statements[] = 'ADD COLUMN invitee_id INT NULL AFTER inviter_id';
            }

            if (!isset($columns['target_word'])) {
                $statements[] = 'ADD COLUMN target_word VARCHAR(6) NOT NULL AFTER invitee_id';
            }

            if (!isset($columns['status'])) {
                $statements[] = "ADD COLUMN status ENUM('pending','active','won','lost','timeout','cancelled') NOT NULL DEFAULT 'pending' AFTER target_word";
            } elseif (strpos(strtolower($columns['status']), "enum('pending','active','won','lost','timeout','cancelled')") === false) {
                $statements[] = "MODIFY COLUMN status ENUM('pending','active','won','lost','timeout','cancelled') NOT NULL DEFAULT 'pending'";
            }

            if (!isset($columns['max_attempts'])) {
                $statements[] = 'ADD COLUMN max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 8 AFTER status';
            }

            if (!isset($columns['current_turn'])) {
                $statements[] = 'ADD COLUMN current_turn TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER max_attempts';
            }

            if (!isset($columns['winner_id'])) {
                $statements[] = 'ADD COLUMN winner_id INT NULL AFTER current_turn';
            }

            if (!isset($columns['created_at'])) {
                $statements[] = 'ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER winner_id';
            }

            if (!isset($columns['updated_at'])) {
                $statements[] = 'ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at';
            }

            if (!isset($columns['started_at'])) {
                $statements[] = 'ADD COLUMN started_at DATETIME NULL AFTER updated_at';
            }

            if (!isset($columns['last_guess_at'])) {
                $statements[] = 'ADD COLUMN last_guess_at DATETIME NULL AFTER started_at';
            }

            if (!isset($columns['ended_at'])) {
                $statements[] = 'ADD COLUMN ended_at DATETIME NULL AFTER last_guess_at';
            }

            if ($statements) {
                $this->conn->exec('ALTER TABLE game ' . implode(', ', $statements));
            }
        } catch (PDOException|Exception $e) {
            logWithDate('Game schema init failed', $e->getMessage());
        }
    }

    /**
     * @return array<string, string>
     */
    private function getExistingColumns(string $table): array
    {
        try {
            $stmt = $this->conn->prepare('SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
            $stmt->bindParam(':table', $table);
            $stmt->execute();

            $columns = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
                $columns[strtolower($column['COLUMN_NAME'])] = strtolower($column['COLUMN_TYPE']);
            }

            return $columns;
        } catch (PDOException|Exception $e) {
            logWithDate('Game schema lookup failed', $e->getMessage());
            return [];
        }
    }

    public function createGame(int $inviterId, int $inviteeId, string $targetWord, int $maxAttempts = 8): ?int
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'INSERT INTO game (inviter_id, invitee_id, target_word, status, max_attempts, current_turn, created_at, updated_at) ';
            $query .= 'VALUES (:inviter_id, :invitee_id, :target_word, :status, :max_attempts, :current_turn, NOW(), NOW())';

            $stmt = $this->conn->prepare($query);
            $status = 'pending';
            $currentTurn = 1;

            $stmt->bindParam(':inviter_id', $inviterId, PDO::PARAM_INT);
            $stmt->bindParam(':invitee_id', $inviteeId, PDO::PARAM_INT);
            $stmt->bindParam(':target_word', $targetWord);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':max_attempts', $maxAttempts, PDO::PARAM_INT);
            $stmt->bindParam(':current_turn', $currentTurn, PDO::PARAM_INT);
            $stmt->execute();

            return (int) $this->conn->lastInsertId();
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());

            return null;
        }
    }

    public function addPlayer(int $gameId, int $playerId): bool
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'UPDATE game SET invitee_id = COALESCE(invitee_id, :player_id), updated_at = NOW() WHERE id = :game_id';
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
            $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());

            return false;
        }
    }

    public function acceptGame(int $gameId): bool
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'UPDATE game SET status = :status, started_at = NOW(), last_guess_at = NOW(), updated_at = NOW() WHERE id = :game_id';
            $stmt = $this->conn->prepare($query);
            $status = 'active';
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());

            return false;
        }
    }

    public function findById(int $gameId): ?array
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'SELECT * FROM game WHERE id = :game_id';
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch();

            return $result ?: null;
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());

            return null;
        }
    }

    public function getCurrentState(int $gameId): ?array
    {
        return $this->findById($gameId);
    }

    public function isPlayerInGame(int $gameId, int $playerId): bool
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'SELECT COUNT(*) AS total FROM game WHERE id = :game_id AND (inviter_id = :player_id OR invitee_id = :player_id)';
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
            $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch();

            return isset($result['total']) && (int) $result['total'] > 0;
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());

            return false;
        }
    }

    public function updateLastGuessAt(int $gameId): bool
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'UPDATE game SET last_guess_at = NOW(), updated_at = NOW() WHERE id = :game_id';
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());

            return false;
        }
    }

    public function setCurrentTurn(int $gameId, int $turn): bool
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'UPDATE game SET current_turn = :turn, updated_at = NOW() WHERE id = :game_id';
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':turn', $turn, PDO::PARAM_INT);
            $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());

            return false;
        }
    }

    public function markWon(int $gameId, int $winnerId): bool
    {
        return $this->setStatusWithWinner($gameId, 'won', $winnerId);
    }

    public function markLost(int $gameId): bool
    {
        return $this->setStatusWithWinner($gameId, 'lost', null);
    }

    public function markTimeout(int $gameId, ?int $winnerId = null): bool
    {
        return $this->setStatusWithWinner($gameId, 'timeout', $winnerId);
    }

    private function setStatusWithWinner(int $gameId, string $status, ?int $winnerId): bool
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'UPDATE game SET status = :status, winner_id = :winner_id, ended_at = NOW(), updated_at = NOW() WHERE id = :game_id';
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':status', $status);
            if ($winnerId === null) {
                $stmt->bindValue(':winner_id', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindParam(':winner_id', $winnerId, PDO::PARAM_INT);
            }
            $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());

            return false;
        }
    }

    public function getLastGuessAt(int $gameId): ?string
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'SELECT last_guess_at FROM game WHERE id = :game_id';
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch();

            return $result['last_guess_at'] ?? null;
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());

            return null;
        }
    }
}
