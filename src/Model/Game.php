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
        } catch (Exception $e) {
            logWithDate('DB connection failed', $e->getMessage());
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
