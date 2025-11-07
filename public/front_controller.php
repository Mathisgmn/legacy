<?php

header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit(0);
}

require_once __DIR__ . '/../helpers/global_helper.php';
require_once __DIR__ . '/../helpers/http_response_helper.php';
require_once __DIR__ . '/../helpers/jwt_helper.php';
require_once __DIR__ . '/../helpers/game_helper.php';
require_once __DIR__ . '/../src/Security/JwtService.php';
require_once __DIR__ . '/../src/Controller/UserController.php';
require_once __DIR__ . '/../src/Controller/GameController.php';

$userController = new UserController();
$jwtService = new JwtService();
$gameController = new GameController();

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];

if (!str_starts_with($requestUri, '/api/')) {
    sendResponse403();
} elseif ($requestUri === '/api/login') {
    switch ($requestMethod) {
        case 'POST':
            $userController->authenticate();
            break;
        default:
            sendResponse405();
            break;
    }
} elseif ($requestUri === '/api/token/refresh') {
    switch ($requestMethod) {
        case 'POST':
            $userController->reauthenticate();
            break;
        default:
            sendResponse405();
            break;
    }
} elseif ($requestUri === '/api/user' && $requestMethod === 'POST') {
    $userController->create();
} else {
    $authenticatedUserId = null;
    $payload = authenticateRequest($jwtService);
    if (!$payload) {
        sendResponse401();
        exit(1);
    } else {
        try {
            $authenticatedUserId = (int) $payload['user_id'];
            $user = $userController->getUser()->findById($authenticatedUserId);
//            sendResponseCustom('Successfully retrieved user data');
        } catch (Exception $e) {
            sendResponse404();
            exit(1);
        }
    }
    if ($requestUri === '/api/logout') {
        switch ($requestMethod) {
            case 'POST':
                $userController->deauthenticate($authenticatedUserId);
                break;
            default:
                sendResponse405();
                break;
        }
    } elseif ($requestUri === '/api/user') {
        switch ($requestMethod) {
            case 'GET':
                $userController->list();
                break;
            default:
                sendResponse405();
                break;
        }
    } elseif (preg_match('#^/api/user/(\d+)$#', $requestUri, $matches)) {
        $userId = (int) $matches[1];
        switch ($requestMethod) {
            case 'GET':
                $userController->get($userId);
                break;
            case 'PUT':
                $userController->replace($userId);
                break;
            case 'PATCH':
                if (!isset($authenticatedUserId) || $authenticatedUserId !== $userId) {
                    sendResponse403();
                    break;
                }
                $userController->update($userId, $authenticatedUserId);
                break;
            case 'DELETE':
                if (!isset($authenticatedUserId) || $authenticatedUserId !== $userId) {
                    sendResponse403();
                    break;
                }
                $userController->delete($userId, $authenticatedUserId);
                break;
            default:
                sendResponse405();
                break;
        }
    } elseif ($requestUri === '/api/game/available-players') {
        switch ($requestMethod) {
            case 'GET':
                $gameController->listAvailablePlayers($authenticatedUserId);
                break;
            default:
                sendResponse405();
                break;
        }
    } elseif ($requestUri === '/api/game') {
        switch ($requestMethod) {
            case 'POST':
                $gameController->create($authenticatedUserId);
                break;
            default:
                sendResponse405();
                break;
        }
    } elseif ($requestUri === '/api/game/current') {
        switch ($requestMethod) {
            case 'GET':
                $gameController->current($authenticatedUserId);
                break;
            default:
                sendResponse405();
                break;
        }
    } elseif (preg_match('#^/api/game/(\\d+)/guess$#', $requestUri, $matches)) {
        $gameId = (int)$matches[1];
        switch ($requestMethod) {
            case 'POST':
                $gameController->submitGuess($gameId, $authenticatedUserId);
                break;
            default:
                sendResponse405();
                break;
        }
    } elseif (preg_match('#^/api/game/(\\d+)/forfeit$#', $requestUri, $matches)) {
        $gameId = (int)$matches[1];
        switch ($requestMethod) {
            case 'POST':
                $gameController->forfeit($gameId, $authenticatedUserId);
                break;
            default:
                sendResponse405();
                break;
        }
    } else {
        sendResponse404();
    }
}