<?php

require_once dirname(__DIR__) . '/Core/Database.php';
require_once dirname(__DIR__, 2) . '/helpers/global_helper.php';

class GameGuess
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

    public function storeGuess(int $gameId, int $playerId, string $guessWord, array $resultPattern, int $attemptNumber, bool $isCorrect): ?int
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'INSERT INTO game_guess (game_id, player_id, guess_word, result_pattern, attempt_number, is_correct, created_at) ';
            $query .= 'VALUES (:game_id, :player_id, :guess_word, :result_pattern, :attempt_number, :is_correct, NOW())';

            $stmt = $this->conn->prepare($query);
            $encodedPattern = json_encode($resultPattern, JSON_THROW_ON_ERROR);
            $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
            $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
            $stmt->bindParam(':guess_word', $guessWord);
            $stmt->bindParam(':result_pattern', $encodedPattern);
            $stmt->bindParam(':attempt_number', $attemptNumber, PDO::PARAM_INT);
            $isCorrectInt = $isCorrect ? 1 : 0;
            $stmt->bindParam(':is_correct', $isCorrectInt, PDO::PARAM_INT);
            $stmt->execute();

            return (int) $this->conn->lastInsertId();
        } catch (JsonException|PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());

            return null;
        }
    }

    public function getGuessesForGame(int $gameId): array
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'SELECT id, game_id, player_id, guess_word, result_pattern, attempt_number, is_correct, created_at ';
            $query .= 'FROM game_guess WHERE game_id = :game_id ORDER BY attempt_number ASC';
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
            $stmt->execute();

            $guesses = $stmt->fetchAll();
            if (!$guesses) {
                return [];
            }

            return array_map(function (array $guess) {
                try {
                    $guess['result_pattern'] = json_decode($guess['result_pattern'], true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    $guess['result_pattern'] = [];
                }

                $guess['is_correct'] = (bool) $guess['is_correct'];

                return $guess;
            }, $guesses);
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());

            return [];
        }
    }

    public function countGuessesForGame(int $gameId): int
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'SELECT COUNT(*) AS total FROM game_guess WHERE game_id = :game_id';
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch();

            return isset($result['total']) ? (int) $result['total'] : 0;
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());

            return 0;
        }
    }

    public function getLastGuessForGame(int $gameId): ?array
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'SELECT id, game_id, player_id, guess_word, result_pattern, attempt_number, is_correct, created_at ';
            $query .= 'FROM game_guess WHERE game_id = :game_id ORDER BY attempt_number DESC LIMIT 1';
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch();
            if (!$result) {
                return null;
            }

            try {
                $result['result_pattern'] = json_decode($result['result_pattern'], true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                $result['result_pattern'] = [];
            }

            $result['is_correct'] = (bool) $result['is_correct'];

            return $result;
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());

            return null;
        }
    }
}
