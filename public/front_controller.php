<?php

header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Expose-Headers: X-Access-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit(0);
}

require_once __DIR__ . '/../helpers/global_helper.php';
require_once __DIR__ . '/../helpers/http_response_helper.php';
require_once __DIR__ . '/../helpers/jwt_helper.php';
require_once __DIR__ . '/../src/Security/JwtService.php';
require_once __DIR__ . '/../src/Controller/UserController.php';
require_once __DIR__ . '/../src/Controller/GameController.php';

$jwtService = new JwtService();
$userController = new UserController($jwtService);
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
    $payload = authenticateRequest($jwtService, $userController);
    if (!$payload) {
        sendResponse401();
        exit(1);
    } else {
        try {
            $userId = $payload['user_id'];
            $user = $userController->getUser()->findById($userId);
//            sendResponseCustom('Successfully retrieved user data');
        } catch (Exception $e) {
            sendResponse404();
            exit(1);
        }
        $userController->keepPresenceAlive((int) $userId);
    }
    if ($requestUri === '/api/logout') {
        switch ($requestMethod) {
            case 'POST':
                $userController->deauthenticate($userId);
                break;
            default:
                sendResponse405();
                break;
        }
    } elseif ($requestUri === '/api/users/online') {
        switch ($requestMethod) {
            case 'GET':
                $userController->listOnline((int) $userId);
                break;
            default:
                sendResponse405();
                break;
        }
    } elseif ($requestUri === '/api/game/invitations') {
        switch ($requestMethod) {
            case 'GET':
                $userController->listInvitations((int) $userId);
                break;
            default:
                sendResponse405();
                break;
        }
    } elseif (preg_match('#^/api/game/(\d+)/invite$#', $requestUri, $matches)) {
        switch ($requestMethod) {
            case 'POST':
                $gameId = (int) $matches[1];
                $userController->inviteToGame($gameId, (int) $userId);
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
    } elseif ($requestUri === '/api/game' && $requestMethod === 'POST') {
        $gameController->create((int) $userId);
    } elseif (preg_match('#^/api/game/(\d+)/accept$#', $requestUri, $matches)) {
        $gameId = (int) $matches[1];
        if ($requestMethod === 'POST') {
            $gameController->accept($gameId, (int) $userId);
        } else {
            sendResponse405();
        }
    } elseif (preg_match('#^/api/game/(\d+)/guess$#', $requestUri, $matches)) {
        $gameId = (int) $matches[1];
        if ($requestMethod === 'POST') {
            $gameController->submitGuess($gameId, (int) $userId);
        } else {
            sendResponse405();
        }
    } elseif (preg_match('#^/api/game/(\d+)/timeout$#', $requestUri, $matches)) {
        $gameId = (int) $matches[1];
        if ($requestMethod === 'POST') {
            $gameController->timeout($gameId, (int) $userId);
        } else {
            sendResponse405();
        }
    } elseif (preg_match('#^/api/game/(\d+)$#', $requestUri, $matches)) {
        $gameId = (int) $matches[1];
        if ($requestMethod === 'GET') {
            $gameController->show($gameId, (int) $userId);
        } else {
            sendResponse405();
        }
    } elseif (preg_match('#^/api/user/(\d+)$#', $requestUri, $matches)) {
        $requestedUserId = (int) $matches[1];
        if ($requestedUserId !== (int) $userId) {
            sendResponseCustom('Access denied: you can only act on your own account', null, 'Error', 403);
            exit(1);
        }
        switch ($requestMethod) {
            case 'GET':
                $userController->get($requestedUserId);
                break;
            case 'PUT':
                $userController->replace($requestedUserId);
                break;
            case 'PATCH':
                $userController->update($requestedUserId);
                break;
            case 'DELETE':
                $userController->delete($requestedUserId);
                break;
            default:
                sendResponse405();
                break;
        }
    } else {
        sendResponse404();
    }
}