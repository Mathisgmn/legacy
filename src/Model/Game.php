<?php

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

    public function create(int $playerOneId, int $playerTwoId, string $secretWord, int $maxAttempts = 8, int $turnDuration = 8): ?array
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'INSERT INTO game ';
            $query .= '(player1_id, player2_id, secret_word, status, current_turn, max_attempts, turn_duration, created_at, updated_at, turn_started_at) ';
            $query .= 'VALUES (:player1_id, :player2_id, :secret_word, :status, :current_turn, :max_attempts, :turn_duration, NOW(), NOW(), NOW())';

            $stmt = $this->conn->prepare($query);
            $status = 'in_progress';
            $currentTurn = $playerOneId;
            $secretWord = strtoupper($secretWord);

            $stmt->bindParam(':player1_id', $playerOneId, PDO::PARAM_INT);
            $stmt->bindParam(':player2_id', $playerTwoId, PDO::PARAM_INT);
            $stmt->bindParam(':secret_word', $secretWord);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':current_turn', $currentTurn, PDO::PARAM_INT);
            $stmt->bindParam(':max_attempts', $maxAttempts, PDO::PARAM_INT);
            $stmt->bindParam(':turn_duration', $turnDuration, PDO::PARAM_INT);
            $stmt->execute();

            $gameId = (int)$this->conn->lastInsertId();
            return $this->findById($gameId);
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());
            return null;
        }
    }

    public function findActiveForUser(int $userId): ?array
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'SELECT * FROM game WHERE status = :status AND (player1_id = :user_id OR player2_id = :user_id) ORDER BY created_at DESC LIMIT 1';

            $stmt = $this->conn->prepare($query);
            $status = 'in_progress';
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch();
            return $result ?: null;
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());
            return null;
        }
    }

    public function findById(int $gameId): ?array
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'SELECT g.*, ';
            $query .= 'u1.pseudo AS player1_pseudo, u1.avatar AS player1_avatar, ';
            $query .= 'u2.pseudo AS player2_pseudo, u2.avatar AS player2_avatar ';
            $query .= 'FROM game g ';
            $query .= 'JOIN user u1 ON u1.id = g.player1_id ';
            $query .= 'JOIN user u2 ON u2.id = g.player2_id ';
            $query .= 'WHERE g.id = :game_id';

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

    public function addGuess(int $gameId, int $playerId, string $guessWord, array $feedback): void
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'INSERT INTO game_guess (game_id, player_id, guess_word, feedback, created_at) VALUES (:game_id, :player_id, :guess_word, :feedback, NOW())';

            $stmt = $this->conn->prepare($query);
            $guessWord = strtoupper($guessWord);
            $feedbackJson = json_encode($feedback);

            $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
            $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
            $stmt->bindParam(':guess_word', $guessWord);
            $stmt->bindParam(':feedback', $feedbackJson);
            $stmt->execute();
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());
        }
    }

    public function getGuesses(int $gameId): array
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'SELECT gg.*, u.pseudo AS player_pseudo FROM game_guess gg JOIN user u ON u.id = gg.player_id WHERE gg.game_id = :game_id ORDER BY gg.created_at ASC, gg.id ASC';

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());
            return [];
        }
    }

    public function countGuesses(int $gameId): int
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'SELECT COUNT(*) AS total FROM game_guess WHERE game_id = :game_id';

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch();
            return $result ? (int)$result['total'] : 0;
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());
            return 0;
        }
    }

    public function switchTurn(int $gameId, int $nextPlayerId): void
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'UPDATE game SET current_turn = :next_turn, updated_at = NOW(), turn_started_at = NOW() WHERE id = :game_id';

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':next_turn', $nextPlayerId, PDO::PARAM_INT);
            $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());
        }
    }

    public function markWinner(int $gameId, int $winnerId): void
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'UPDATE game SET status = :status, winner_id = :winner_id, current_turn = NULL, ended_at = NOW(), updated_at = NOW() WHERE id = :game_id';

            $stmt = $this->conn->prepare($query);
            $status = 'won';
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':winner_id', $winnerId, PDO::PARAM_INT);
            $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());
        }
    }

    public function markDraw(int $gameId): void
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'UPDATE game SET status = :status, current_turn = NULL, ended_at = NOW(), updated_at = NOW() WHERE id = :game_id';

            $stmt = $this->conn->prepare($query);
            $status = 'draw';
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());
        }
    }

    public function markForfeit(int $gameId, int $winnerId): void
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'UPDATE game SET status = :status, winner_id = :winner_id, current_turn = NULL, ended_at = NOW(), updated_at = NOW() WHERE id = :game_id';

            $stmt = $this->conn->prepare($query);
            $status = 'forfeit';
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':winner_id', $winnerId, PDO::PARAM_INT);
            $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());
        }
    }
}