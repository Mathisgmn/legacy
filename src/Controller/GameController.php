<?php

require_once __DIR__ . '/../Model/Game.php';
require_once __DIR__ . '/../Model/GameGuess.php';
require_once __DIR__ . '/../Model/User.php';

class GameController
{
    private Game $game;
    private GameGuess $gameGuess;
    private User $userModel;
    private int $timeLimitSeconds = 8;

    public function __construct(?Game $game = null, ?GameGuess $gameGuess = null, ?User $userModel = null)
    {
        $this->game = $game ?? new Game();
        $this->gameGuess = $gameGuess ?? new GameGuess();
        $this->userModel = $userModel ?? new User();
    }

    public function create(int $inviterId): void
    {
        try {
            $data = $this->getRequestData();
            $opponentId = (int) ($data['opponent_id'] ?? 0);
            $targetWord = strtoupper(trim($data['target_word'] ?? ''));

            if ($opponentId <= 0 || $opponentId === $inviterId) {
                sendResponseCustom('Invalid opponent provided', null, 'Error', 400);
                return;
            }

            if (!self::isValidWord($targetWord)) {
                sendResponseCustom('Target word must contain exactly 6 letters', null, 'Error', 400);
                return;
            }

            $opponent = $this->userModel->findById($opponentId);
            if (!$opponent) {
                sendResponseCustom('Opponent not found', null, 'Error', 404);
                return;
            }

            $gameId = $this->game->createGame($inviterId, $opponentId, $targetWord);
            if (!$gameId) {
                sendResponse500();
                return;
            }

            $this->game->addPlayer($gameId, $opponentId);

            $state = $this->buildGameState($gameId);
            sendResponseCustom('Game invitation created', $state, 'Success', 201);
        } catch (Exception $e) {
            logWithDate('Game creation failed', $e->getMessage());
            sendResponse500();
        }
    }

    public function accept(int $gameId, int $playerId): void
    {
        try {
            $game = $this->game->findById($gameId);
            if (!$game) {
                sendResponse404();
                return;
            }

            if ((int) $game['invitee_id'] !== $playerId) {
                sendResponseCustom('Only the invited player can accept this game', null, 'Error', 403);
                return;
            }

            if ($game['status'] !== 'pending') {
                sendResponseCustom('Game is not pending invitation', null, 'Error', 400);
                return;
            }

            if (!$this->game->acceptGame($gameId)) {
                sendResponse500();
                return;
            }

            $state = $this->buildGameState($gameId);
            sendResponseCustom('Game accepted', $state);
        } catch (Exception $e) {
            logWithDate('Game accept failed', $e->getMessage());
            sendResponse500();
        }
    }

    public function submitGuess(int $gameId, int $playerId): void
    {
        try {
            $game = $this->game->findById($gameId);
            if (!$game) {
                sendResponse404();
                return;
            }

            if (!$this->game->isPlayerInGame($gameId, $playerId)) {
                sendResponseCustom('You are not allowed to interact with this game', null, 'Error', 403);
                return;
            }

            if ($game['status'] !== 'active') {
                sendResponseCustom('Game is not active', null, 'Error', 400);
                return;
            }

            $lastGuessAt = $this->game->getLastGuessAt($gameId);
            if ($lastGuessAt && (time() - strtotime($lastGuessAt)) > $this->timeLimitSeconds) {
                $winnerId = ((int) $game['inviter_id'] === $playerId) ? (int) $game['invitee_id'] : (int) $game['inviter_id'];
                $this->game->markTimeout($gameId, $winnerId);
                sendResponseCustom('Game has timed out', null, 'Error', 409);
                return;
            }

            $data = $this->getRequestData();
            $guessWord = strtoupper(trim($data['guess'] ?? ''));

            if (!self::isValidWord($guessWord)) {
                sendResponseCustom('Guess must contain exactly 6 letters', null, 'Error', 400);
                return;
            }

            $maxAttempts = isset($game['max_attempts']) ? (int) $game['max_attempts'] : 8;
            $attemptCount = $this->gameGuess->countGuessesForGame($gameId);
            if ($attemptCount >= $maxAttempts) {
                sendResponseCustom('Maximum number of attempts reached', null, 'Error', 409);
                return;
            }

            $resultPattern = self::evaluateGuessPattern($game['target_word'], $guessWord);
            $attemptNumber = $attemptCount + 1;
            $isCorrect = $this->isGuessCorrect($resultPattern);

            $guessId = $this->gameGuess->storeGuess($gameId, $playerId, $guessWord, $resultPattern, $attemptNumber, $isCorrect);
            if (!$guessId) {
                sendResponse500();
                return;
            }

            $this->game->updateLastGuessAt($gameId);
            $this->game->setCurrentTurn($gameId, $attemptNumber + 1);

            $outcome = self::determineStatusFromGuess($isCorrect, $attemptNumber, $maxAttempts);
            if ($outcome === 'won') {
                $this->game->markWon($gameId, $playerId);
            } elseif ($outcome === 'lost') {
                $this->game->markLost($gameId);
            }

            $state = $this->buildGameState($gameId);
            $message = $this->buildGuessMessage($outcome);
            sendResponseCustom($message, $state);
        } catch (Exception $e) {
            logWithDate('Game guess failed', $e->getMessage());
            sendResponse500();
        }
    }

    public function show(int $gameId, int $playerId): void
    {
        try {
            $game = $this->game->findById($gameId);
            if (!$game) {
                sendResponse404();
                return;
            }

            if (!$this->game->isPlayerInGame($gameId, $playerId)) {
                sendResponseCustom('You are not allowed to view this game', null, 'Error', 403);
                return;
            }

            $state = $this->buildGameState($gameId);
            sendResponseCustom('Game state retrieved', $state);
        } catch (Exception $e) {
            logWithDate('Game retrieval failed', $e->getMessage());
            sendResponse500();
        }
    }

    public function timeout(int $gameId, int $playerId): void
    {
        try {
            $game = $this->game->findById($gameId);
            if (!$game) {
                sendResponse404();
                return;
            }

            if (!$this->game->isPlayerInGame($gameId, $playerId)) {
                sendResponseCustom('You are not allowed to modify this game', null, 'Error', 403);
                return;
            }

            if ($game['status'] !== 'active') {
                sendResponseCustom('Only active games can be timed out', null, 'Error', 400);
                return;
            }

            $winnerId = ((int) $game['inviter_id'] === $playerId) ? (int) $game['invitee_id'] : (int) $game['inviter_id'];
            $this->game->markTimeout($gameId, $winnerId);

            $state = $this->buildGameState($gameId);
            sendResponseCustom('Game has been marked as timed out', $state);
        } catch (Exception $e) {
            logWithDate('Game timeout failed', $e->getMessage());
            sendResponse500();
        }
    }

    public static function isValidWord(string $word): bool
    {
        if ($word === '') {
            return false;
        }

        return (bool) preg_match('/^[A-Z]{6}$/', strtoupper($word));
    }

    public static function evaluateGuessPattern(string $targetWord, string $guessWord): array
    {
        $targetWord = strtoupper($targetWord);
        $guessWord = strtoupper($guessWord);
        $targetLetters = str_split($targetWord);
        $guessLetters = str_split($guessWord);
        $length = count($targetLetters);
        $result = [];
        $remaining = [];

        for ($i = 0; $i < $length; $i++) {
            $letter = $targetLetters[$i];
            if (!isset($remaining[$letter])) {
                $remaining[$letter] = 0;
            }
            $remaining[$letter]++;
        }

        for ($i = 0; $i < $length; $i++) {
            $guessLetter = $guessLetters[$i];
            if ($guessLetter === $targetLetters[$i]) {
                $result[$i] = [
                    'letter' => $guessLetter,
                    'color' => 'correct',
                ];
                $remaining[$guessLetter]--;
            }
        }

        for ($i = 0; $i < $length; $i++) {
            if (isset($result[$i])) {
                continue;
            }

            $guessLetter = $guessLetters[$i];
            $color = 'absent';
            if (isset($remaining[$guessLetter]) && $remaining[$guessLetter] > 0) {
                $color = 'present';
                $remaining[$guessLetter]--;
            }

            $result[$i] = [
                'letter' => $guessLetter,
                'color' => $color,
            ];
        }

        ksort($result);

        return array_values($result);
    }

    public static function determineStatusFromGuess(bool $isCorrect, int $attemptNumber, int $maxAttempts): ?string
    {
        if ($isCorrect) {
            return 'won';
        }

        if ($attemptNumber >= $maxAttempts) {
            return 'lost';
        }

        return null;
    }

    private function getRequestData(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (is_array($data)) {
            return $data;
        }

        if (!empty($_POST)) {
            return $_POST;
        }

        return [];
    }

    private function buildGameState(int $gameId): array
    {
        $game = $this->game->getCurrentState($gameId) ?? [];
        $guesses = $this->gameGuess->getGuessesForGame($gameId);

        return [
            'game' => $game,
            'guesses' => $guesses,
        ];
    }

    private function isGuessCorrect(array $pattern): bool
    {
        foreach ($pattern as $item) {
            if (($item['color'] ?? '') !== 'correct') {
                return false;
            }
        }

        return true;
    }

    private function buildGuessMessage(?string $outcome): string
    {
        return match ($outcome) {
            'won' => 'Correct guess! You win the game.',
            'lost' => 'Maximum attempts reached. Game over.',
            default => 'Guess registered',
        };
    }
}
