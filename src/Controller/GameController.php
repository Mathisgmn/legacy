<?php

require_once __DIR__ . '/../Model/Game.php';
require_once __DIR__ . '/../Model/UserStatus.php';
require_once __DIR__ . '/../Core/WordProvider.php';
require_once __DIR__ . '/../../helpers/game_helper.php';

class GameController
{
    private Game $game;
    private UserStatus $userStatus;
    private const int WORD_LENGTH = 6;
    private const int MAX_ATTEMPTS = 8;
    private const int TURN_DURATION = 8;

    public function __construct()
    {
        $this->game = new Game();
        $this->userStatus = new UserStatus();
    }

    public function listAvailablePlayers(int $authenticatedUserId): void
    {
        $this->userStatus->setOnline($authenticatedUserId);
        $players = $this->userStatus->listAvailableOpponents($authenticatedUserId);
        sendResponseCustom('Joueurs connectés récupérés.', $players);
    }

    public function create(int $authenticatedUserId): void
    {
        $opponentId = (int)($_POST['opponent_id'] ?? 0);

        if ($opponentId <= 0) {
            sendResponseCustom('Aucun adversaire sélectionné.', null, 'Error', 400);
            return;
        }

        if ($opponentId === $authenticatedUserId) {
            sendResponseCustom('Vous ne pouvez pas démarrer une partie contre vous-même.', null, 'Error', 400);
            return;
        }

        $existingGame = $this->game->findActiveForUser($authenticatedUserId);
        if ($existingGame) {
            sendResponseCustom('Vous avez déjà une partie en cours.', null, 'Error', 400);
            return;
        }

        if (!$this->userStatus->isUserAvailable($opponentId)) {
            sendResponseCustom('L’adversaire choisi n’est pas disponible.', null, 'Error', 409);
            return;
        }

        $secretWord = WordProvider::randomWord();
        $createdGame = $this->game->create($authenticatedUserId, $opponentId, $secretWord, self::MAX_ATTEMPTS, self::TURN_DURATION);

        if (!$createdGame) {
            sendResponseCustom('Impossible de démarrer la partie.', null, 'Error', 500);
            return;
        }

        $gameId = (int)$createdGame['id'];
        $this->userStatus->setInGame($authenticatedUserId, $gameId);
        $this->userStatus->setInGame($opponentId, $gameId);

        $details = $this->game->findById($gameId);
        $guesses = $this->game->getGuesses($gameId);

        sendResponseCustom('Partie créée.', $this->formatGame($details, $guesses, $authenticatedUserId));
    }

    public function current(int $authenticatedUserId): void
    {
        $activeGame = $this->game->findActiveForUser($authenticatedUserId);

        if (!$activeGame) {
            sendResponseCustom('Aucune partie en cours.', null);
            return;
        }

        $gameId = (int)$activeGame['id'];
        $details = $this->game->findById($gameId);
        $guesses = $this->game->getGuesses($gameId);

        sendResponseCustom('Partie chargée.', $this->formatGame($details, $guesses, $authenticatedUserId));
    }

    public function submitGuess(int $gameId, int $authenticatedUserId): void
    {
        $payload = json_decode(file_get_contents('php://input'), true) ?? [];
        $word = strtoupper(trim($payload['word'] ?? ''));

        if (strlen($word) !== self::WORD_LENGTH) {
            sendResponseCustom('Le mot doit contenir exactement six lettres.', null, 'Error', 400);
            return;
        }

        $game = $this->game->findById($gameId);
        if (!$game) {
            sendResponse404();
            return;
        }

        if (!$this->isPlayerInGame($game, $authenticatedUserId)) {
            sendResponse403();
            return;
        }

        if ($game['status'] !== 'in_progress') {
            sendResponseCustom('La partie est terminée.', null, 'Error', 400);
            return;
        }

        if ((int)$game['current_turn'] !== $authenticatedUserId) {
            sendResponseCustom('Ce n’est pas votre tour.', null, 'Error', 403);
            return;
        }

        $totalAttempts = $this->game->countGuesses($gameId);
        if ($totalAttempts >= (int)$game['max_attempts']) {
            sendResponseCustom('Nombre maximal de tentatives atteint.', null, 'Error', 400);
            return;
        }

        $result = evaluateMotusGuess($game['secret_word'], $word);
        $this->game->addGuess($gameId, $authenticatedUserId, $word, $result['feedback']);

        $totalAttempts++;
        $message = 'Proposition enregistrée.';
        $otherPlayerId = $this->getOtherPlayerId($game, $authenticatedUserId);

        if ($result['isWinning']) {
            $this->game->markWinner($gameId, $authenticatedUserId);
            $this->userStatus->clearGame($authenticatedUserId);
            $this->userStatus->clearGame($otherPlayerId);
            $message = 'Mot trouvé !';
        } elseif ($totalAttempts >= (int)$game['max_attempts']) {
            $this->game->markDraw($gameId);
            $this->userStatus->clearGame($authenticatedUserId);
            $this->userStatus->clearGame($otherPlayerId);
            $message = 'Nombre maximal de tentatives atteint.';
        } else {
            $this->game->switchTurn($gameId, $otherPlayerId);
        }

        $details = $this->game->findById($gameId);
        $guesses = $this->game->getGuesses($gameId);

        sendResponseCustom($message, $this->formatGame($details, $guesses, $authenticatedUserId));
    }

    public function forfeit(int $gameId, int $authenticatedUserId): void
    {
        $game = $this->game->findById($gameId);
        if (!$game) {
            sendResponse404();
            return;
        }

        if (!$this->isPlayerInGame($game, $authenticatedUserId)) {
            sendResponse403();
            return;
        }

        if ($game['status'] !== 'in_progress') {
            sendResponseCustom('La partie est déjà terminée.', null, 'Error', 400);
            return;
        }

        $otherPlayerId = $this->getOtherPlayerId($game, $authenticatedUserId);
        $this->game->markForfeit($gameId, $otherPlayerId);
        $this->userStatus->clearGame($authenticatedUserId);
        $this->userStatus->clearGame($otherPlayerId);

        $details = $this->game->findById($gameId);
        $guesses = $this->game->getGuesses($gameId);

        sendResponseCustom('Vous avez abandonné la partie.', $this->formatGame($details, $guesses, $authenticatedUserId));
    }

    private function formatGame(?array $game, array $guesses, int $viewerId): ?array
    {
        if (!$game) {
            return null;
        }

        $maxAttempts = (int)($game['max_attempts'] ?? self::MAX_ATTEMPTS);
        $turnDuration = (int)($game['turn_duration'] ?? self::TURN_DURATION);
        $turnStartedAt = $game['turn_started_at'] ?? null;
        $turnExpiresAt = null;
        if ($turnStartedAt) {
            $turnExpiresAt = date('c', strtotime($turnStartedAt) + $turnDuration);
        }

        $formattedGuesses = array_map(static function (array $guess): array {
            $feedback = json_decode($guess['feedback'], true);
            if (!is_array($feedback)) {
                $feedback = [];
            }

            return [
                'id' => (int)$guess['id'],
                'player_id' => (int)$guess['player_id'],
                'player_pseudo' => $guess['player_pseudo'],
                'word' => $guess['guess_word'],
                'feedback' => $feedback,
                'created_at' => date('c', strtotime($guess['created_at'])),
            ];
        }, $guesses);

        return [
            'id' => (int)$game['id'],
            'status' => $game['status'],
            'player_turn_id' => isset($game['current_turn']) ? (int)$game['current_turn'] : null,
            'is_viewer_turn' => isset($game['current_turn']) && (int)$game['current_turn'] === $viewerId,
            'player1' => [
                'id' => (int)$game['player1_id'],
                'pseudo' => $game['player1_pseudo'],
                'avatar' => $game['player1_avatar'] ?? null,
            ],
            'player2' => [
                'id' => (int)$game['player2_id'],
                'pseudo' => $game['player2_pseudo'],
                'avatar' => $game['player2_avatar'] ?? null,
            ],
            'guesses' => $formattedGuesses,
            'max_attempts' => $maxAttempts,
            'attempts_used' => count($formattedGuesses),
            'remaining_attempts' => max(0, $maxAttempts - count($formattedGuesses)),
            'turn_started_at' => $turnStartedAt ? date('c', strtotime($turnStartedAt)) : null,
            'turn_expires_at' => $turnExpiresAt,
            'word_length' => self::WORD_LENGTH,
            'winner_id' => isset($game['winner_id']) ? (int)$game['winner_id'] : null,
        ];
    }

    private function isPlayerInGame(array $game, int $playerId): bool
    {
        return (int)$game['player1_id'] === $playerId || (int)$game['player2_id'] === $playerId;
    }

    private function getOtherPlayerId(array $game, int $playerId): int
    {
        return (int)$game['player1_id'] === $playerId ? (int)$game['player2_id'] : (int)$game['player1_id'];
    }
}