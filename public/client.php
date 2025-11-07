<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client jQuery</title>
    <link rel="stylesheet" href="/assets/css/pico.min.css">
    <link rel="stylesheet" href="/assets/css/app.css">
    <script src="/assets/js/jquery-3.7.1.min.js"></script>
</head>
<body>
<header class="page-header container">
    <h1>Client jQuery</h1>
    <button id="theme-toggle" class="theme-toggle" type="button" aria-label="Basculer le mode d'affichage">
        <span class="toggle-icon" aria-hidden="true">🌙</span>
        <span class="toggle-label">Mode sombre</span>
    </button>
</header>
<main class="container page-content">

    <section id="authentication">
        <form id="auth-form">
            <fieldset>
                <label for="auth-email">Adresse courriel</label>
                <input type="email" id="auth-email" name="email" required>
            </fieldset>
            <fieldset>
                <label for="auth-password">Mot de passe</label>
                <input type="password" id="auth-password" name="password" required>
            </fieldset>
            <button type="submit">Se connecter</button>
        </form>

        <button id="logout-button" type="button" style="display: none;">Se déconnecter</button>

        <p id="auth-message"></p>
    </section>

    <section id="registration">
        <h2>Créer un compte</h2>
        <form id="register-form">
            <fieldset>
                <label for="register-first-name">Prénom</label>
                <input type="text" id="register-first-name" name="first_name" required>
            </fieldset>
            <fieldset>
                <label for="register-last-name">Nom</label>
                <input type="text" id="register-last-name" name="last_name" required>
            </fieldset>
            <fieldset>
                <label for="register-pseudo">Pseudo</label>
                <input type="text" id="register-pseudo" name="pseudo" required>
            </fieldset>
            <fieldset>
                <label for="register-birth-date">Date de naissance</label>
                <input type="date" id="register-birth-date" name="birth_date" required>
            </fieldset>
            <fieldset>
                <label for="register-gender">Genre</label>
                <select id="register-gender" name="gender" required>
                    <option value="" selected disabled>-- Sélectionnez --</option>
                    <option value="m">Masculin</option>
                    <option value="f">Féminin</option>
                    <option value="o">Autre</option>
                </select>
            </fieldset>
            <fieldset>
                <label for="register-avatar">Avatar</label>
                <input type="text" id="register-avatar" name="avatar" required>
            </fieldset>
            <fieldset>
                <label for="register-email">Adresse courriel</label>
                <input type="email" id="register-email" name="email" required>
            </fieldset>
            <fieldset>
                <label for="register-password">Mot de passe</label>
                <input type="password" id="register-password" name="password" required>
            </fieldset>
            <button type="submit">Créer le compte</button>
        </form>
        <p id="register-message"></p>
    </section>

    <section id="game-selector">
        <h2>Jeux disponibles</h2>
        <article class="game-card" aria-labelledby="motus-card-title">
            <h3 id="motus-card-title">Motus (multijoueur)</h3>
            <p>
                Affrontez un autre joueur en proposant des mots de six lettres tour à tour.
                Les lettres bien placées s'affichent en rouge, les lettres mal placées en jaune.
            </p>
            <button type="button" id="open-motus-button" disabled>Accéder à Motus</button>
            <p class="game-card-status" id="motus-card-status">Connectez-vous pour lancer une partie de Motus.</p>
        </article>
    </section>

    <section id="profile-section" style="display: none;">
        <h2>Modifier mon compte</h2>
        <form id="profile-form">
            <fieldset>
                <label for="profile-pseudo">Pseudo</label>
                <input type="text" id="profile-pseudo" name="pseudo">
            </fieldset>
            <fieldset>
                <label for="profile-gender">Genre</label>
                <select id="profile-gender" name="gender">
                    <option value="" selected disabled>-- Sélectionnez --</option>
                    <option value="m">Masculin</option>
                    <option value="f">Féminin</option>
                    <option value="o">Autre</option>
                </select>
            </fieldset>
            <fieldset>
                <label for="profile-avatar">Avatar</label>
                <input type="text" id="profile-avatar" name="avatar">
            </fieldset>
            <button type="submit">Mettre à jour</button>
        </form>
        <hr>
        <button id="delete-account-button" type="button">Supprimer mon compte</button>
        <p id="profile-message"></p>
    </section>

    <section id="motus-wrapper">
        <section id="lobby-section">
            <h2>Joueurs disponibles</h2>
            <p id="lobby-info">Sélectionnez un joueur connecté pour démarrer une partie.</p>
            <ul id="available-players-list"></ul>
            <p id="lobby-message"></p>
        </section>

        <section id="game-section">
            <h2>Motus</h2>
            <div id="game-status-bar">
                <span id="game-opponents"></span>
                <span id="game-turn"></span>
                <span id="turn-countdown"></span>
                <span id="attempts-counter"></span>
            </div>
            <div class="motus-board" id="game-grid"></div>
            <form id="guess-form">
                <fieldset>
                    <label for="guess-word">Votre proposition (6 lettres)</label>
                    <input type="text" id="guess-word" name="guess-word" maxlength="6" minlength="6" autocomplete="off"
                           required>
                </fieldset>
                <button type="submit">Valider</button>
                <button type="button" id="forfeit-button" class="secondary">Abandonner</button>
            </form>
            <p id="game-message"></p>
        </section>
    </section>
</main>
<script>
    const THEME_STORAGE_KEY = 'legacybeat-theme';
    const rootElement = document.body;
    const themeToggleButton = document.getElementById('theme-toggle');

    function applyTheme(theme) {
        const normalizedTheme = theme === 'dark' ? 'dark' : 'light';
        rootElement.setAttribute('data-theme', normalizedTheme);
        localStorage.setItem(THEME_STORAGE_KEY, normalizedTheme);

        const icon = normalizedTheme === 'dark' ? '☀️' : '🌙';
        const label = normalizedTheme === 'dark' ? 'Mode clair' : 'Mode sombre';

        themeToggleButton.querySelector('.toggle-icon').textContent = icon;
        themeToggleButton.querySelector('.toggle-label').textContent = label;
    }

    function toggleTheme() {
        const currentTheme = rootElement.getAttribute('data-theme');
        applyTheme(currentTheme === 'dark' ? 'light' : 'dark');
    }

    const savedTheme = localStorage.getItem(THEME_STORAGE_KEY);
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    applyTheme(savedTheme || (prefersDark ? 'dark' : 'light'));

    themeToggleButton.addEventListener('click', toggleTheme);

    let dataSample = {
        first_name: "Adrien",
        last_name: "Girard",
        pseudo: "CodeRanger",
        birth_date: "2004-12-03",
        gender: "m",
        avatar: "coderanger.jpg",
        email: "adrien.girard@example.com",
        password: "password"
    };

    let partialDataSample = {
        pseudo: "Avalonee",
        avatar: "avalonee.jpg"
    };

    var currentUserId = null;
    var currentUserPseudo = null;
    var refreshDeferred = null;
    var lobbyIntervalId = null;
    var gameIntervalId = null;
    var turnCountdownIntervalId = null;
    var currentGameId = null;
    var lastKnownGameState = null;
    var GAME_POLL_INTERVAL = 3000;
    var LOBBY_POLL_INTERVAL = 5000;

    function setAccessToken(token) {
        localStorage.setItem('accessToken', token);
    }

    function getAccessToken() {
        return localStorage.getItem('accessToken');
    }

    function clearAccessToken() {
        localStorage.removeItem('accessToken');
    }

    function isAccessTokenExpired(token) {
        var payload = decodeAccessToken(token);

        if (!payload || !payload.exp) {
            return true;
        }

        var now = Math.floor(Date.now() / 1000);
        return payload.exp <= now;
    }

    function handleTokenRefreshFailure() {
        clearAccessToken();
        onDeauthentication('Votre session a expiré. Veuillez vous reconnecter.');
    }

    function requestTokenRefresh() {
        if (!refreshDeferred) {
            var deferred = $.Deferred();
            refreshDeferred = deferred;

            $.ajax({
                url: 'http://localhost:8000/api/token/refresh',
                method: 'POST',
                success: function (response) {
                    var accessToken = response.data && response.data.accessToken;
                    if (accessToken) {
                        setAccessToken(accessToken);
                        deferred.resolve(accessToken);
                    } else {
                        deferred.reject();
                    }
                },
                error: function (xhr) {
                    deferred.reject(xhr);
                },
                complete: function () {
                    refreshDeferred = null;
                }
            });
        }

        return refreshDeferred.promise();
    }

    function ensureValidAccessToken(onSuccess, onFailure) {
        var accessToken = getAccessToken();

        if (!accessToken) {
            if (typeof onFailure === 'function') {
                onFailure();
            }
            return;
        }

        if (!isAccessTokenExpired(accessToken)) {
            if (typeof onSuccess === 'function') {
                onSuccess(accessToken);
            }
            return;
        }

        requestTokenRefresh()
            .done(function (newToken) {
                if (typeof onSuccess === 'function') {
                    onSuccess(newToken);
                }
            })
            .fail(function (xhr) {
                handleTokenRefreshFailure();
                if (typeof onFailure === 'function') {
                    onFailure(xhr);
                }
            });
    }

    function withValidToken(callback, onFailure) {
        ensureValidAccessToken(function (token) {
            if (typeof callback === 'function') {
                callback(token);
            }
        }, onFailure);
    }

    function startLobbyPolling() {
        if (lobbyIntervalId) {
            return;
        }

        fetchAvailablePlayers();
        lobbyIntervalId = setInterval(fetchAvailablePlayers, LOBBY_POLL_INTERVAL);
    }

    function stopLobbyPolling() {
        if (lobbyIntervalId) {
            clearInterval(lobbyIntervalId);
            lobbyIntervalId = null;
        }
    }

    function startGamePolling() {
        if (gameIntervalId) {
            return;
        }

        fetchCurrentGame();
        gameIntervalId = setInterval(fetchCurrentGame, GAME_POLL_INTERVAL);
    }

    function stopGamePolling() {
        if (gameIntervalId) {
            clearInterval(gameIntervalId);
            gameIntervalId = null;
        }
    }

    function stopTurnCountdown() {
        if (turnCountdownIntervalId) {
            clearInterval(turnCountdownIntervalId);
            turnCountdownIntervalId = null;
        }
        $('#turn-countdown').text('');
    }

    function startTurnCountdown(turnExpiresAt, isViewerTurn) {
        stopTurnCountdown();

        if (!turnExpiresAt || !isViewerTurn) {
            return;
        }

        function updateCountdown() {
            var now = new Date();
            var target = new Date(turnExpiresAt);
            var diff = target.getTime() - now.getTime();

            if (isNaN(diff) || diff <= 0) {
                $('#turn-countdown').text('Temps écoulé');
                stopTurnCountdown();
                return;
            }

            var seconds = Math.ceil(diff / 1000);
            $('#turn-countdown').text('Temps restant : ' + seconds + ' s');
        }

        updateCountdown();
        turnCountdownIntervalId = setInterval(updateCountdown, 1000);
    }

    function fetchAvailablePlayers() {
        if (!currentUserId) {
            return;
        }

        withValidToken(function (token) {
            $.ajax({
                url: 'http://localhost:8000/api/game/available-players',
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + token,
                },
                success: function (response) {
                    renderAvailablePlayers(response.data || []);
                    if (response.message) {
                        $('#lobby-message').text('');
                    }
                },
                error: function (xhr) {
                    if (xhr.status === 401) {
                        reauthenticate(function () {
                            fetchAvailablePlayers();
                        });
                    }
                }
            });
        });
    }

    function renderAvailablePlayers(players) {
        var list = $('#available-players-list');
        list.empty();

        var canChallenge = !lastKnownGameState || lastKnownGameState.status !== 'in_progress';

        if (!players.length) {
            var message = canChallenge ? 'Aucun autre joueur connecté pour le moment.' : 'La liste sera disponible à la fin de votre partie.';
            list.append($('<li></li>').text(message));
            return;
        }

        players.forEach(function (player) {
            var button = $('<button type="button" class="contrast start-game-button"></button>');
            button.text(player.pseudo || 'Joueur ' + player.id);
            button.attr('data-user-id', player.id);
            var opponentBusy = player.is_in_game === 1 || player.is_in_game === '1';
            if (!canChallenge || opponentBusy) {
                button.prop('disabled', true);
            }

            var content = $('<div class="player-entry"></div>');
            content.append(button);

            if (opponentBusy) {
                button.addClass('secondary');
                content.append($('<small class="player-status busy"></small>').text('En partie'));
                content.append($('<span class="visually-hidden"></span>').text('Joueur en partie'));
            }

            var listItem = $('<li></li>');
            listItem.append(content)
            list.append(listItem);
        });
    }

    function startGameAgainst(opponentId) {
        if (!opponentId) {
            return;
        }

        withValidToken(function (token) {
            $.ajax({
                url: 'http://localhost:8000/api/game',
                method: 'POST',
                data: {opponent_id: opponentId},
                headers: {
                    'Authorization': 'Bearer ' + token,
                },
                success: function (response) {
                    if (response.data) {
                        handleGameUpdate(response.data, response.message);
                    }
                    $('#lobby-message').text(response.message || '');
                },
                error: function (xhr) {
                    if (xhr.status === 401) {
                        reauthenticate(function () {
                            startGameAgainst(opponentId);
                        });
                        return;
                    }

                    var message = 'Impossible de démarrer la partie.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    $('#lobby-message').text(message);
                }
            });
        });
    }

    function fetchCurrentGame() {
        if (!currentUserId) {
            return;
        }

        withValidToken(function (token) {
            $.ajax({
                url: 'http://localhost:8000/api/game/current',
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + token,
                },
                success: function (response) {
                    handleGameUpdate(response.data, response.data ? response.message : null);
                },
                error: function (xhr) {
                    if (xhr.status === 401) {
                        reauthenticate(function () {
                            fetchCurrentGame();
                        });
                    }
                }
            });
        });
    }

    function renderGameGrid(game) {
        var board = $('#game-grid');
        board.empty();

        var totalRows = game.max_attempts || 0;
        var wordLength = game.word_length || 6;
        var guesses = game.guesses || [];

        for (var i = 0; i < totalRows; i++) {
            var row = $('<div class="motus-row"></div>');
            var guess = guesses[i];

            if (guess) {
                var feedback = Array.isArray(guess.feedback) ? guess.feedback : [];
                for (var j = 0; j < wordLength; j++) {
                    var cellInfo = feedback[j] || {letter: '', status: 'empty'};
                    var cell = $('<div class="motus-cell"></div>');
                    var statusClass = cellInfo.status || 'empty';
                    if (statusClass !== 'empty') {
                        cell.addClass(statusClass);
                    } else {
                        cell.addClass('empty');
                    }
                    cell.text((cellInfo.letter || '').toUpperCase());
                    row.append(cell);
                }
            } else {
                for (var j = 0; j < wordLength; j++) {
                    var emptyCell = $('<div class="motus-cell empty"></div>');
                    emptyCell.text('');
                    row.append(emptyCell);
                }
            }

            board.append(row);
        }
    }

    function setGuessFormAvailability(game) {
        var inProgress = game.status === 'in_progress';
        var isPlayerTurn = inProgress && game.is_viewer_turn;

        if (inProgress) {
            $('#guess-form').show();
        } else {
            $('#guess-form').hide();
            $('#guess-form')[0].reset();
        }

        $('#guess-form').toggleClass('active', inProgress);

        $('#guess-word').prop('disabled', !isPlayerTurn);
        $('#guess-form button[type="submit"]').prop('disabled', !isPlayerTurn);
        $('#forfeit-button').prop('disabled', !inProgress);

        if (isPlayerTurn) {
            $('#guess-word').focus();
        }
    }

    function handleGameUpdate(game, message) {
        if (!game) {
            currentGameId = null;
            lastKnownGameState = null;
            stopTurnCountdown();
            $('#game-section').hide();
            $('#motus-wrapper').show();
            $('#lobby-section').show();
            $('#game-grid').empty();
            $('#game-opponents').text('');
            $('#game-turn').text('');
            $('#attempts-counter').text('');
            $('#game-message').text(message || 'Aucune partie en cours.');
            $('#forfeit-button').prop('disabled', true);
            return;
        }

        lastKnownGameState = game;
        currentGameId = game.id;
        $('#motus-wrapper').show();

        if (game.status === 'in_progress') {
            $('#lobby-section').hide();
        } else {
            $('#lobby-section').show();
        }

        $('#game-section').show();

        var opponentsText = '';
        if (game.player1 && game.player2) {
            opponentsText = (game.player1.pseudo || 'Joueur 1') + ' vs ' + (game.player2.pseudo || 'Joueur 2');
        }
        $('#game-opponents').text(opponentsText);

        $('#attempts-counter').text('Tentatives : ' + game.attempts_used + '/' + game.max_attempts);

        var turnText = '';
        if (game.status === 'in_progress') {
            if (game.is_viewer_turn) {
                turnText = 'À votre tour !';
            } else {
                var currentPseudo = '';
                if (game.player_turn_id === game.player1.id) {
                    currentPseudo = game.player1.pseudo;
                } else if (game.player_turn_id === game.player2.id) {
                    currentPseudo = game.player2.pseudo;
                }
                turnText = currentPseudo ? 'Tour de ' + currentPseudo : 'Tour de l’adversaire';
            }
        } else if (game.status === 'won') {
            var winnerPseudo = '';
            if (game.winner_id === game.player1.id) {
                winnerPseudo = game.player1.pseudo;
            } else if (game.winner_id === game.player2.id) {
                winnerPseudo = game.player2.pseudo;
            }
            turnText = winnerPseudo ? ('Victoire de ' + winnerPseudo + ' !') : 'Partie remportée.';
        } else if (game.status === 'draw') {
            turnText = 'Aucun gagnant : le nombre maximal de tentatives a été atteint.';
        } else if (game.status === 'forfeit') {
            var winner = '';
            if (game.winner_id === game.player1.id) {
                winner = game.player1.pseudo;
            } else if (game.winner_id === game.player2.id) {
                winner = game.player2.pseudo;
            }
            turnText = winner ? ('Victoire de ' + winner + ' par abandon.') : 'Partie terminée par abandon.';
        } else {
            turnText = 'Partie terminée.';
        }

        $('#game-turn').text(turnText);

        renderGameGrid(game);
        setGuessFormAvailability(game);

        if (game.status === 'in_progress') {
            startTurnCountdown(game.turn_expires_at, game.is_viewer_turn);
        } else {
            stopTurnCountdown();
        }

        if (message) {
            $('#game-message').text(message);
        } else if (game.status !== 'in_progress') {
            $('#game-message').text(turnText);
        } else {
            $('#game-message').text('');
        }
    }

    function clearGameUI() {
        currentGameId = null;
        lastKnownGameState = null;
        stopTurnCountdown();
        $('#motus-wrapper').hide();
        $('#lobby-section').hide();
        $('#available-players-list').empty();
        $('#lobby-message').text('');
        $('#game-section').hide();
        $('#game-grid').empty();
        $('#game-message').text('');
        $('#game-turn').text('');
        $('#game-opponents').text('');
        $('#attempts-counter').text('');
        $('#guess-form')[0].reset();
        $('#guess-form').hide();
        $('#forfeit-button').prop('disabled', true);
    }

    function updateMotusAccessUI() {
        var isAuthenticated = Boolean(currentUserId);
        var button = $('#open-motus-button');
        var status = $('#motus-card-status');

        if (isAuthenticated) {
            button.prop('disabled', false);
            status.text('Cliquez sur «\u00a0Accéder à Motus\u00a0» pour rejoindre le lobby et défier un joueur connecté.');
        } else {
            button.prop('disabled', true);
            status.text('Connectez-vous pour lancer une partie de Motus.');
        }
    }

    function revealMotusSection() {
        $('#motus-wrapper').show();

        if (!lastKnownGameState) {
            $('#lobby-section').show();
        }

        var motusOffset = $('#motus-wrapper').offset();
        if (motusOffset) {
            $('html, body').animate({scrollTop: motusOffset.top - 20}, 400);
        }
    }

    function submitGuess(word) {
        if (!currentGameId) {
            return;
        }

        var sanitizedWord = (word || '').toUpperCase().trim();

        if (sanitizedWord.length !== 6) {
            $('#game-message').text('Votre mot doit comporter exactement six lettres.');
            return;
        }

        withValidToken(function (token) {
            $.ajax({
                url: 'http://localhost:8000/api/game/' + currentGameId + '/guess',
                method: 'POST',
                data: JSON.stringify({word: sanitizedWord}),
                contentType: 'application/json',
                headers: {
                    'Authorization': 'Bearer ' + token,
                },
                success: function (response) {
                    $('#guess-word').val('');
                    if (response.data) {
                        handleGameUpdate(response.data, response.message);
                    } else if (response.message) {
                        $('#game-message').text(response.message);
                    }
                },
                error: function (xhr) {
                    if (xhr.status === 401) {
                        reauthenticate(function () {
                            submitGuess(sanitizedWord);
                        });
                        return;
                    }

                    var message = 'La proposition n’a pas pu être enregistrée.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    $('#game-message').text(message);
                }
            });
        });
    }

    function forfeitGame() {
        if (!currentGameId) {
            return;
        }

        var confirmed = window.confirm('Voulez-vous abandonner la partie ?');
        if (!confirmed) {
            return;
        }

        withValidToken(function (token) {
            $.ajax({
                url: 'http://localhost:8000/api/game/' + currentGameId + '/forfeit',
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token,
                },
                success: function (response) {
                    if (response.data) {
                        handleGameUpdate(response.data, response.message);
                    } else if (response.message) {
                        $('#game-message').text(response.message);
                    }
                },
                error: function (xhr) {
                    if (xhr.status === 401) {
                        reauthenticate(function () {
                            forfeitGame();
                        });
                        return;
                    }

                    var message = 'Impossible d’abandonner la partie.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    $('#game-message').text(message);
                }
            });
        });
    }


    function decodeAccessToken(token) {
        if (!token) {
            return null;
        }

        var parts = token.split('.');
        if (parts.length !== 3) {
            return null;
        }

        var payload = parts[1].replace(/-/g, '+').replace(/_/g, '/');
        while (payload.length % 4 !== 0) {
            payload += '=';
        }

        try {
            return JSON.parse(atob(payload));
        } catch (error) {
            return null;
        }
    }

    function extractUser(data) {
        if (!data) {
            return null;
        }

        if (Array.isArray(data)) {
            return data.length ? data[0] : null;
        }

        return data;
    }

    function authenticate(email, password) {
        $.ajax({
            url: 'http://localhost:8000/api/login',
            method: 'POST',
            data: {email: email, password: password},
            success: function (response) {
                var accessToken = response.data.accessToken;
                setAccessToken(accessToken);
                onAuthenticationSuccess('Authentification réussie.');
            },
            error: function () {
                onAuthenticationError('Les informations de connexion sont invalides.');
            }
        });
    }

    function deauthenticate() {
        withValidToken(function (token) {
            $.ajax({
                url: 'http://localhost:8000/api/logout',
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token,
                },
                success: function () {
                    clearAccessToken();
                    onDeauthentication('Vous avez été déconnecté.');
                },
                error: function () {
                    updateMessage('La déconnexion a échoué.');
                }
            });
        }, function () {
            updateMessage('Aucune session active.');
        });
    }

    function reauthenticate(callback) {
        return requestTokenRefresh()
            .done(function (token) {
                if (typeof callback === 'function') {
                    callback(token);
                }
            })
            .fail(function () {
                handleTokenRefreshFailure();
            });
    }

    function endpointList() {
        withValidToken(function (token) {
            $.ajax({
                url: 'http://localhost:8000/api/user',
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + token,
                },
                success: function () {
                    // TODO Do something if needed
                },
                error: function () {
                    // TODO Do something if needed
                }
            });
        });
    }

    function endpointGet(id) {
        withValidToken(function (token) {
            $.ajax({
                url: 'http://localhost:8000/api/user' + '/' + id,
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + token,
                },
                success: function () {
                    // TODO Do something if needed
                },
                error: function () {
                    // TODO Do something if needed
                }
            });
        });
    }

    function endpointCreate(data) {
        withValidToken(function (token) {
            $.ajax({
                url: 'http://localhost:8000/api/user',
                method: 'POST',
                data: data,
                headers: {
                    'Authorization': 'Bearer ' + token,
                },
                success: function () {
                    // TODO Do something if needed
                },
                error: function () {
                    // TODO Do something if needed
                }
            });
        });
    }

    function registerUser(data) {
        $.ajax({
            url: 'http://localhost:8000/api/user',
            method: 'POST',
            data: data,
            success: function (response) {
                onRegistrationSuccess('Compte créé avec succès.', response.data);
            },
            error: function (xhr) {
                var message = 'La création du compte a échoué.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                onRegistrationError(message);
            }
        });
    }

    function endpointUpdate(id, data) {
        withValidToken(function (token) {
            $.ajax({
                url: 'http://localhost:8000/api/user' + '/' + id,
                method: 'PATCH',
                data: JSON.stringify(data),
                contentType: 'application/json',
                headers: {
                    'Authorization': 'Bearer ' + token,
                },
                success: function () {
                    // TODO Do something if needed
                },
                error: function () {
                    // TODO Do something if needed
                }
            });
        });
    }

    function endpointReplace(id) {
        // TODO Nothing to do
    }

    function endpointDelete(id) {
        withValidToken(function (token) {
            $.ajax({
                url: 'http://localhost:8000/api/user' + '/' + id,
                method: 'DELETE',
                headers: {
                    'Authorization': 'Bearer ' + token,
                },
                success: function () {
                    // TODO Do something if needed
                },
                error: function () {
                    // TODO Do something if needed
                }
            });
        });
    }

    /*
     * Example of a sequence of actions:
     *   - 4
     *   - 1 (with valid credentials)
     *   - 4
     *   - 1 (with invalid credentials)
     *   - 4
     *   - 1 (with valid credentials)
     *   - 4
     *   - 2
     *   - 4
     *   - 1 (with valid credentials, then wait for 5 minutes or change JWT_TTL value)
     *   - 3
     *   - 2
     *   - 3
     */
    const action = null;
    switch (action) {
        case 1:
            authenticate("lucas.morel@example.com", "password");
            break;
        case 2:
            deauthenticate();
            break;
        case 3:
            reauthenticate();
            break;
        case 4:
            endpointList();
            break;
        case 5:
            endpointGet(1);
            break;
        case 6:
            endpointCreate(dataSample);
            break;
        case 7:
            endpointUpdate(1, partialDataSample);
            break;
        case 8:
            // endpointReplace(1);
            // TODO Nothing to doo
            break;
        case 9:
            endpointDelete(6);
            break;
        default:
            console.log('Unknown action number: ' + action);
            break;
    }

    function fetchUserProfile(userId) {
        withValidToken(function (token) {
            $.ajax({
                url: 'http://localhost:8000/api/user/' + userId,
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + token,
                },
                success: function (response) {
                    var user = extractUser(response.data);
                    if (user) {
                        currentUserPseudo = user.pseudo || null;
                        fillProfileForm(user);
                        $('#profile-section').show();
                        updateProfileMessage('');
                    } else {
                        updateProfileMessage('Impossible de charger le profil utilisateur.');
                    }
                },
                error: function (xhr) {
                    if (xhr.status === 401) {
                        reauthenticate(function () {
                            fetchUserProfile(userId);
                        });
                        return;
                    }
                    updateProfileMessage('Impossible de charger le profil utilisateur.');
                }
            });
        }, function () {
            updateProfileMessage('Impossible de charger le profil utilisateur.');
        });
    }

    function loadAuthenticatedUser() {
        var token = getAccessToken();
        var payload = decodeAccessToken(token);

        if (!payload || !payload.user_id) {
            return;
        }

        currentUserId = payload.user_id;
        currentUserPseudo = null;
        updateMotusAccessUI()
        fetchUserProfile(currentUserId);
    }

    function onAuthenticationSuccess(message) {
        $('#auth-form')[0].reset();
        $('#auth-form').hide();
        $('#logout-button').show();
        $('#registration').hide();
        updateMessage(message);
        loadAuthenticatedUser();
        stopLobbyPolling();
        stopGamePolling();
        startLobbyPolling();
        startGamePolling();
        $('#motus-wrapper').show();
        updateMotusAccessUI();
    }

    function onAuthenticationError(message) {
        $('#auth-form').show();
        $('#logout-button').hide();
        $('#registration').show();
        updateMessage(message);
        currentUserId = null;
        currentUserPseudo = null;
        $('#profile-section').hide();
        clearProfileForm();
        stopLobbyPolling();
        stopGamePolling();
        clearGameUI();
        updateMotusAccessUI();
    }

    function onDeauthentication(message) {
        $('#auth-form')[0].reset();
        $('#auth-form').show();
        $('#logout-button').hide();
        $('#registration').show();
        updateMessage(message);
        currentUserId = null;
        currentUserPseudo = null;
        $('#profile-section').hide();
        clearProfileForm();
        stopLobbyPolling();
        stopGamePolling();
        clearGameUI();
        updateMotusAccessUI();
    }

    function updateMessage(message) {
        $('#auth-message').text(message);
    }

    function onRegistrationSuccess(message, data) {
        $('#register-form')[0].reset();
        var createdUser = extractUser(data);
        if (createdUser && createdUser.email) {
            $('#auth-email').val(createdUser.email);
        }
        updateRegisterMessage(message);
    }

    function onRegistrationError(message) {
        updateRegisterMessage(message);
    }

    function updateRegisterMessage(message) {
        $('#register-message').text(message);
    }

    function fillProfileForm(user) {
        $('#profile-pseudo').val(user.pseudo || '');
        if (user.gender) {
            $('#profile-gender').val(user.gender);
        } else {
            $('#profile-gender')[0].selectedIndex = 0;
        }
        $('#profile-avatar').val(user.avatar || '');
    }

    function clearProfileForm() {
        $('#profile-form')[0].reset();
        $('#profile-gender')[0].selectedIndex = 0;
        updateProfileMessage('');
    }

    function updateProfileMessage(message) {
        $('#profile-message').text(message);
    }

    function updateUserProfile(data) {
        if (!currentUserId) {
            updateProfileMessage('Aucun utilisateur authentifié.');
            return;
        }

        withValidToken(function (token) {
            $.ajax({
                url: 'http://localhost:8000/api/user/' + currentUserId,
                method: 'PATCH',
                data: JSON.stringify(data),
                contentType: 'application/json',
                headers: {
                    'Authorization': 'Bearer ' + token,
                },
                success: function (response) {
                    var user = extractUser(response.data);
                    if (user) {
                        fillProfileForm(user);
                    }
                    onProfileUpdateSuccess('Profil mis à jour.', user);
                },
                error: function (xhr) {
                    if (xhr.status === 401) {
                        reauthenticate(function () {
                            updateUserProfile(data);
                        });
                        return;
                    }

                    var message = 'La mise à jour du profil a échoué.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    onProfileUpdateError(message);
                }
            });
        }, function () {
            onProfileUpdateError('La mise à jour du profil a échoué.');
        });
    }

    function onProfileUpdateSuccess(message) {
        updateProfileMessage(message);
    }

    function onProfileUpdateError(message) {
        updateProfileMessage(message);
    }

    function deleteUserAccount() {
        if (!currentUserId) {
            updateProfileMessage('Aucun utilisateur authentifié.');
            return;
        }

        var confirmed = window.confirm('Êtes-vous sûr de vouloir supprimer votre compte ? Cette action est définitive.');

        if (!confirmed) {
            return;
        }

        performAccountDeletion();
    }

    function performAccountDeletion() {
        withValidToken(function (token) {
            $.ajax({
                url: 'http://localhost:8000/api/user/' + currentUserId,
                method: 'DELETE',
                headers: {
                    'Authorization': 'Bearer ' + token,
                },
                success: function () {
                    onAccountDeletionSuccess('Compte supprimé.');
                },
                error: function (xhr) {
                    if (xhr.status === 401) {
                        reauthenticate(function () {
                            performAccountDeletion();
                        });
                        return;
                    }

                    var message = 'La suppression du compte a échoué.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    onAccountDeletionError(message);
                }
            });
        }, function () {
            onAccountDeletionError('La suppression du compte a échoué.');
        });
    }

    function onAccountDeletionSuccess(message) {
        clearAccessToken();
        onDeauthentication(message);
        updateRegisterMessage('');
    }

    function onAccountDeletionError(message) {
        updateProfileMessage(message);
    }

    $(function () {
        $('#auth-form').on('submit', function (event) {
            event.preventDefault();
            var email = $('#auth-email').val();
            var password = $('#auth-password').val();
            authenticate(email, password);
        });

        $('#logout-button').on('click', function () {
            deauthenticate();
        });

        $('#register-form').on('submit', function (event) {
            event.preventDefault();
            var registrationData = {
                first_name: $('#register-first-name').val(),
                last_name: $('#register-last-name').val(),
                pseudo: $('#register-pseudo').val(),
                birth_date: $('#register-birth-date').val(),
                gender: $('#register-gender').val(),
                avatar: $('#register-avatar').val(),
                email: $('#register-email').val(),
                password: $('#register-password').val(),
            };
            registerUser(registrationData);
        });

        $('#profile-form').on('submit', function (event) {
            event.preventDefault();

            var updateData = {};
            var pseudo = $('#profile-pseudo').val();
            var gender = $('#profile-gender').val();
            var avatar = $('#profile-avatar').val();

            if (pseudo) {
                updateData.pseudo = pseudo;
            }
            if (gender) {
                updateData.gender = gender;
            }
            if (avatar) {
                updateData.avatar = avatar;
            }

            if ($.isEmptyObject(updateData)) {
                updateProfileMessage('Veuillez renseigner au moins un champ pour mettre à jour votre profil.');
                return;
            }

            updateUserProfile(updateData);
        });

        $('#delete-account-button').on('click', function () {
            deleteUserAccount();
        });

        $('#available-players-list').on('click', '.start-game-button', function () {
            var opponentId = parseInt($(this).attr('data-user-id'), 10);
            if (!isNaN(opponentId)) {
                startGameAgainst(opponentId);
            }
        });

        $('#guess-form').on('submit', function (event) {
            event.preventDefault();
            var guess = $('#guess-word').val();
            submitGuess(guess);
        });

        $('#forfeit-button').on('click', function () {
            forfeitGame();
        });

        $('#open-motus-button').on('click', function () {
            if ($(this).prop('disabled')) {
                return;
            }
            revealMotusSection();
        });

        clearGameUI();
        updateMotusAccessUI();
        restoreSession();
    });

    function restoreSession() {
        var accessToken = getAccessToken();

        if (!accessToken) {
            return;
        }

        if (isAccessTokenExpired(accessToken)) {
            requestTokenRefresh()
                .done(function () {
                    onAuthenticationSuccess('Session restaurée.');
                })
                .fail(function () {
                    handleTokenRefreshFailure();
                });
            return;
        }

        onAuthenticationSuccess('Session restaurée.');
    }
</script>
</body>
</html>
