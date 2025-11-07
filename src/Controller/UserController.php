<?php

require_once __DIR__ . '/../Model/User.php';
require_once __DIR__ . '/../Model/UserPresence.php';
require_once __DIR__ . '/../Model/GameInvitation.php';

class UserController
{
    private User $user;
    private JwtService $jwtService;
    private UserPresence $presence;
    private GameInvitation $gameInvitation;
    private int $presenceTimeout;

    public function __construct(JwtService $jwtService)
    {
        $this->user = new User();
        $this->jwtService = $jwtService;
        $this->presence = new UserPresence();
        $this->gameInvitation = new GameInvitation();
        $timeout = (int) ($_ENV['USER_PRESENCE_TTL'] ?? 300);
        $this->presenceTimeout = $timeout > 0 ? $timeout : 300;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function keepPresenceAlive(int $userId): void
    {
        $this->presence->touch($userId);
    }

    public function listOnline(int $currentUserId): void
    {
        try {
            $users = $this->presence->getAvailableUsers($currentUserId, $this->presenceTimeout);
            sendResponseCustom('Successfully retrieved available users', $users);
        } catch (Exception $e) {
            logWithDate('Presence retrieval failed', $e->getMessage());
            sendResponse500();
        }
    }

    public function inviteToGame(int $gameId, int $currentUserId): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!is_array($input)) {
                sendResponseCustom('Invalid payload', null, 'Error', 400);
                return;
            }

            $targetUserId = isset($input['target_user_id']) ? (int) $input['target_user_id'] : 0;
            $action = isset($input['action']) ? strtolower((string) $input['action']) : 'send';

            if ($targetUserId <= 0 || $targetUserId === $currentUserId) {
                sendResponseCustom('Invalid target user id', null, 'Error', 400);
                return;
            }

            $result = null;

            switch ($action) {
                case 'send':
                    $result = $this->gameInvitation->send($gameId, $currentUserId, $targetUserId);
                    $this->presence->touch($currentUserId);
                    $this->presence->touch($targetUserId);
                    break;
                case 'cancel':
                    $result = $this->gameInvitation->cancel($gameId, $currentUserId, $targetUserId);
                    if ($result) {
                        $this->presence->updateStatus($currentUserId, UserPresence::STATUS_AVAILABLE);
                        $this->presence->updateStatus($targetUserId, UserPresence::STATUS_AVAILABLE);
                    }
                    break;
                case 'start':
                    $result = $this->gameInvitation->start($gameId, $currentUserId, $targetUserId);
                    if ($result) {
                        $this->presence->updateStatus($currentUserId, UserPresence::STATUS_IN_GAME);
                        $this->presence->updateStatus($targetUserId, UserPresence::STATUS_IN_GAME);
                    }
                    break;
                default:
                    sendResponseCustom('Unsupported invite action', null, 'Error', 400);
                    return;
            }

            if (!$result) {
                sendResponseCustom('Invitation not found or could not be updated', null, 'Error', 404);
                return;
            }

            sendResponseCustom('Invitation updated', $result);
        } catch (Exception $e) {
            logWithDate('Invitation handling failed', $e->getMessage());
            sendResponse500();
        }
    }

    public function list(): void
    {
        try {
            $result = $this->user->findAll();

            sendResponseCustom('Successfully get all user data from database', $result);
        } catch (Exception $e) {
            logWithDate('DB connection failed', $e->getMessage());
            sendResponse500();
        }
    }

    public function get($id): void
    {
        try {
            $result = $this->user->findById($id);

            $httpResponseCode = !$result ? 404 : 200;
            $status = !$result ? 'Error' : 'Success';
            $message = !$result ? 'User not found' : 'Successfully retrieved user data';

            sendResponseCustom($message, $result, $status, $httpResponseCode);
        } catch (Exception $e) {
            logWithDate('DB connection failed', $e->getMessage());
            sendResponse500();
        }
    }

    public function create(): void
    {
        try {
            $firstName = $_POST['first_name'];
            $lastName = $_POST['last_name'];
            $pseudo = $_POST['pseudo'];
            $birthDate = $_POST['birth_date'];
            $gender = $_POST['gender'];
            $avatar = $_POST['avatar'];
            $email = $_POST['email'];
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

            $this->user->create($firstName, $lastName, $pseudo, $birthDate, $gender, $avatar, $email, $password);

            $result = $this->user->findByPseudoOrEmail($pseudo, $email);

            $httpResponseCode = !$result ? 400 : 200;
            $status = !$result ? 'Error' : 'Success';
            $message = !$result ? 'New user creation failed' : 'Successfully create new user';

            sendResponseCustom($message, $result, $status, $httpResponseCode);
        } catch (Exception $e) {
            logWithDate('DB connection failed', $e->getMessage());
            sendResponse500();
        }
    }

    public function update($id): void
    {
        try {
            $refreshToken = $_COOKIE['refresh_token'] ?? null;

            if (!$refreshToken) {
                sendResponseCustom('No refresh token found', null, 'Error', 400);
                exit(1);
            }

            $tokenOwner = $this->user->findByToken($refreshToken);
            if (!$tokenOwner || (int) $tokenOwner['id'] !== (int) $id) {
                sendResponseCustom('Invalid refresh token for this user', null, 'Error', 403);
                exit(1);
            }

            if (!$this->user->findById($id)) {
                sendResponseCustom('User not found', null, 'User not found', 404);
                exit(1);
            }

            $inputData = json_decode(file_get_contents('php://input'), true);

            if (!$inputData) {
                sendResponseCustom('Input data is empty', null, 'Error', 400);
                exit(1);
            }

            $acceptedFields = [
                'pseudo',
                'gender',
                'avatar',
            ];

            $toBeUpdated = [];
            foreach ($acceptedFields as $acceptedField) {
                if (isset($inputData[$acceptedField]) && $inputData[$acceptedField] !== '') {
                    $toBeUpdated[$acceptedField] = $inputData[$acceptedField];
                }
            }

            if (!$toBeUpdated) {
                sendResponseCustom('Nothing to update', null, 'Error', 400);
                exit(1);
            }

            $this->user->setFields($id, $toBeUpdated);
            $result = $this->user->findById($id);

            sendResponseCustom('Successfully update data', $result);
        } catch (Exception $e) {
            logWithDate('DB connection failed', $e->getMessage());
            sendResponse500();
        }
    }

    public function replace($id): void
    {
        // TODO Nothing to do
    }

    public function delete($id): void
    {
        try {
            if (!$this->user->findById($id)) {
                sendResponse404();
                exit(1);
            }

            $this->user->deleteById($id);

            sendResponseCustom('Successfully delete user');
        } catch (Exception $e) {
            logWithDate('DB connection failed', $e->getMessage());
            sendResponse500();
        }
    }

    public function authenticate(): void
    {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        try {
            $user = $this->user->findByEmail($email);

            $badResult = !$user || !password_verify($password, $user['password']);

            $accessToken = null;
            if (!$badResult) {
                $userId = $user['id'];
                $accessToken = $this->jwtService->createToken([
                    'user_id' => $userId,
                    'email' => $email,
                ]);

                $refreshToken = bin2hex(random_bytes(64));
                $expiresIn = $_ENV['JWT_REFRESH_TTL'] ?? 604800; // or 7 days (7*24*60*60)

                $this->user->storeRefreshToken($userId, $refreshToken, $expiresIn);

                $this->presence->updateStatus($userId, UserPresence::STATUS_AVAILABLE);

                setcookie('refresh_token', $refreshToken, [
                    'expires' => time() + $expiresIn,
                    'path' => '/',
                    'httponly' => true,
                    'secure' => false, // true in production (HTTPS)
                    'samesite' => 'Lax',
                ]);
            }

            $httpResponseCode = $badResult ? 401 : 200;
            $status = $badResult ? 'Error' : 'Success';
            $message = $badResult ? 'Invalid credentials' : 'Successfully authenticate user';

            sendResponseCustom($message, ['accessToken' => $accessToken], $status, $httpResponseCode);
        } catch (Exception $e) {
            logWithDate('DB connection failed', $e->getMessage());
            sendResponse500();
        }
    }

    public function deauthenticate($id): void
    {
        $refreshToken = $_COOKIE['refresh_token'] ?? null;

        if (!$refreshToken) {
            sendResponseCustom('No refresh token found', null, 'Error', 400);
            exit(1);
        }

        $this->user->revokeRefreshToken($id, $refreshToken);
        $this->presence->markOffline($id);
        sendResponseCustom('Refresh token revoked');

        setcookie('refresh_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'secure' => false, // true in production (HTTPS)
            'samesite' => 'Lax',
        ]);
    }

    public function reauthenticate(): void
    {
        $refreshToken = $_COOKIE['refresh_token'] ?? null;

        if (!$refreshToken) {
            sendResponseCustom('No refresh token found', null, 'Error', 400);
            exit(1);
        }

        try {
            $result = $this->createAccessTokenFromRefreshToken($refreshToken);
        } catch (Exception $e) {
            logWithDate('Unknown error', $e->getMessage());
            sendResponse500();
            exit(1);
        }

        if (!$result) {
            sendResponseCustom('Invalid or revoked refresh token', null, 'Error', 401);
            exit(1);
        }

        sendResponseCustom('Successfully reauthenticate user', ['accessToken' => $result['accessToken']]);
        exit(0);
    }

    public function refreshAccessTokenFromCookie(): ?array
    {
        $refreshToken = $_COOKIE['refresh_token'] ?? null;

        if (!$refreshToken) {
            return null;
        }

        try {
            return $this->createAccessTokenFromRefreshToken($refreshToken);
        } catch (Exception $e) {
            logWithDate('Unknown error', $e->getMessage());

            return null;
        }
    }

    private function createAccessTokenFromRefreshToken(string $refreshToken): ?array
    {
        $user = $this->user->findByToken($refreshToken);

        if (!$user) {
            return null;
        }

        $payload = ['user_id' => $user['id'], 'email' => $user['email']];
        $accessToken = $this->jwtService->createToken($payload);

        $this->presence->touch((int) $user['id']);

        return [
            'accessToken' => $accessToken,
            'payload' => $payload,
        ];
    }
}
