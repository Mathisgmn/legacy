<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client jQuery</title>
    <link rel="stylesheet" href="/assets/css/pico.min.css">
    <style>
        .inline-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: center;
        }

        .motus-grid {
            display: grid;
            gap: 0.5rem;
            margin-block: 1rem;
        }

        .motus-row {
            display: grid;
            grid-template-columns: repeat(6, minmax(2.5rem, 1fr));
            gap: 0.35rem;
        }

        .motus-cell {
            aspect-ratio: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.35rem;
            font-weight: 700;
            font-size: 1.25rem;
            text-transform: uppercase;
            background: #f5f5f5;
            color: #15212b;
        }

        .motus-cell.cell-correct {
            background: #d7263d;
            color: #fff;
        }

        .motus-cell.cell-present {
            background: #f7b801;
            color: #15212b;
        }

        .motus-cell.cell-absent {
            background: #0091ad;
            color: #fff;
        }

        #guessTimer {
            font-weight: 600;
        }

        #onlinePlayersList button,
        #invitationList button,
        #guessForm button {
            margin-inline-start: 0.5rem;
        }

        #gameStatus {
            font-style: italic;
        }

        #victoryMessage {
            font-weight: 700;
        }
    </style>
    <script src="/assets/js/jquery-3.7.1.min.js"></script>
</head>
<body>
<main class="container">
    <h1>Client jQuery</h1>

    <article>
        <h2>Authentification</h2>
        <form id="loginForm">
            <fieldset role="group">
                <input type="email" id="loginEmail" name="email" placeholder="Email" required />
                <input type="password" id="loginPassword" name="password" placeholder="Mot de passe" required />
                <button type="submit">Se connecter</button>
            </fieldset>
            <small id="loginMsg" aria-live="polite"></small>
        </form>

        <form id="logoutForm" hidden>
            <button type="submit">Se déconnecter</button>
            <small id="logoutMsg" aria-live="polite"></small>
        </form>
    </article>

    <article>
        <h2>Créer un compte</h2>
        <form id="registerForm">
            <div class="grid">
                <input type="text" id="registerFirstName" name="first_name" placeholder="Prénom" required />
                <input type="text" id="registerLastName" name="last_name" placeholder="Nom" required />
            </div>
            <div class="grid">
                <input type="text" id="registerPseudo" name="pseudo" placeholder="Pseudo" required />
                <input type="date" id="registerBirthDate" name="birth_date" required />
            </div>
            <div class="grid">
                <select id="registerGender" name="gender" required>
                    <option value="" disabled selected>Genre</option>
                    <option value="f">Féminin</option>
                    <option value="m">Masculin</option>
                    <option value="o">Autre</option>
                </select>
                <input type="text" id="registerAvatar" name="avatar" placeholder="Avatar (fichier)" required />
            </div>
            <div class="grid">
                <input type="email" id="registerEmail" name="email" placeholder="Email" required />
                <input type="password" id="registerPassword" name="password" placeholder="Mot de passe" required />
            </div>
            <button type="submit">Créer le compte</button>
            <small id="registerMsg" aria-live="polite"></small>
        </form>
    </article>

    <article id="onlinePlayersSection" hidden>
        <h2>Joueurs disponibles</h2>
        <p>Invitez un joueur en ligne pour lancer une partie.</p>
        <ul id="onlinePlayersList"></ul>
        <small id="onlinePlayersMsg" aria-live="polite"></small>
    </article>

    <article id="invitationsSection" hidden>
        <h2>Invitations reçues</h2>
        <p>Acceptez une invitation pour rejoindre une partie Motus.</p>
        <ul id="invitationList"></ul>
        <small id="invitationMsg" aria-live="polite"></small>
    </article>

    <article id="gameSection" hidden>
        <h2>Partie en cours</h2>
        <div id="gameStatus">Aucune partie active.</div>
        <div id="turnInfo"></div>
        <div id="guessTimer" aria-live="polite"></div>
        <div class="motus-grid" id="motusGrid"></div>
        <form id="guessForm" class="inline-actions" hidden>
            <input type="text" id="guessInput" name="guess" maxlength="6" placeholder="Mot de 6 lettres" autocomplete="off" required />
            <button type="submit" id="guessSubmit">Envoyer</button>
            <small id="guessMsg" aria-live="polite"></small>
        </form>
        <div id="victoryMessage" aria-live="polite"></div>
    </article>

    <article>
        <h2>Mettre à jour mon compte</h2>
        <form id="updateForm" hidden>
            <div class="grid">
                <input type="text" id="updatePseudo" name="pseudo" placeholder="Nouveau pseudo" />
                <select id="updateGender" name="gender">
                    <option value="" selected>Genre (inchangé)</option>
                    <option value="f">Féminin</option>
                    <option value="m">Masculin</option>
                    <option value="o">Autre</option>
                </select>
            </div>
            <input type="text" id="updateAvatar" name="avatar" placeholder="Nouvel avatar" />
            <button type="submit">Enregistrer les modifications</button>
            <small id="updateMsg" aria-live="polite"></small>
        </form>
    </article>

    <article>
        <h2>Supprimer mon compte</h2>
        <form id="deleteForm" hidden>
            <button type="submit" class="contrast">Supprimer mon compte</button>
            <small id="deleteMsg" aria-live="polite"></small>
        </form>
    </article>

    <article>
        <h2>Informations de session</h2>
        <p id="sessionInfo">Non authentifié.</p>
    </article>
</main>

<script>
    const API_BASE = 'http://localhost:8000/api';

    const ONLINE_PLAYERS_REFRESH = 10000;
    const INVITATIONS_REFRESH = 12000;
    const GAME_REFRESH = 5000;
    const TURN_TIMER_DURATION = 8;

    let onlinePlayersInterval = null;
    let invitationsInterval = null;
    let gameInterval = null;
    let currentGameId = null;
    let guessTimer = null;
    let guessTimeLeft = 0;
    let currentTurnKey = null;

    function getStoredToken() {
        return localStorage.getItem('accessToken');
    }

    function getStoredUserId() {
        return localStorage.getItem('userId');
    }

    function parseJwt(token) {
        try {
            const payload = token.split('.')[1];
            const base64 = payload.replace(/-/g, '+').replace(/_/g, '/');
            const jsonPayload = decodeURIComponent(atob(base64).split('').map(function (c) {
                return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
            }).join(''));
            return JSON.parse(jsonPayload);
        } catch (error) {
            return null;
        }
    }

    function setAuthenticatedState(isAuthenticated, sessionData = null) {
        $('#loginForm').prop('hidden', isAuthenticated);
        $('#logoutForm').prop('hidden', !isAuthenticated);
        $('#updateForm').prop('hidden', !isAuthenticated);
        $('#deleteForm').prop('hidden', !isAuthenticated);
        $('#onlinePlayersSection').prop('hidden', !isAuthenticated);
        $('#invitationsSection').prop('hidden', !isAuthenticated);
        $('#gameSection').prop('hidden', !isAuthenticated);

        if (isAuthenticated && sessionData) {
            $('#sessionInfo').text(`Connecté en tant qu'utilisateur #${sessionData.userId} (${sessionData.email}).`);
        } else if (isAuthenticated && getStoredUserId()) {
            $('#sessionInfo').text(`Connecté en tant qu'utilisateur #${getStoredUserId()}.`);
        } else {
            $('#sessionInfo').text('Non authentifié.');
        }

        if (isAuthenticated) {
            enableGameplayFeatures();
        } else {
            disableGameplayFeatures();
        }
    }

    function handleAjaxError(xhr, fallbackMessage) {
        const message = xhr.responseJSON?.message || fallbackMessage;
        return message;
    }

    function firstNonNull() {
        for (let i = 0; i < arguments.length; i += 1) {
            const value = arguments[i];
            if (value !== undefined && value !== null && value !== '') {
                return value;
            }
        }
        return '';
    }

    function authenticate(email, password) {
        $.ajax({
            url: `${API_BASE}/login`,
            method: 'POST',
            data: { email: email, password: password },
            xhrFields: {
                withCredentials: true
            },
            success: function (response) {
                const accessToken = response.data?.accessToken;
                if (!accessToken) {
                    $('#loginMsg').text('Réponse inattendue.');
                    return;
                }
                const payload = parseJwt(accessToken);
                localStorage.setItem('accessToken', accessToken);
                if (payload?.user_id) {
                    localStorage.setItem('userId', payload.user_id);
                    localStorage.setItem('userEmail', payload.email || email);
                }
                $('#loginMsg').text('Connexion réussie.');
                setAuthenticatedState(true, { userId: payload?.user_id || '?', email: payload?.email || email });
            },
            error: function (xhr) {
                const message = handleAjaxError(xhr, 'Échec de la connexion.');
                $('#loginMsg').text(message);
            }
        });
    }

    function deauthenticate() {
        $.ajax({
            url: `${API_BASE}/logout`,
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + getStoredToken(),
            },
            xhrFields: {
                withCredentials: true
            },
            success: function () {
                localStorage.removeItem('accessToken');
                localStorage.removeItem('userId');
                localStorage.removeItem('userEmail');
                $('#logoutMsg').text('Déconnexion effectuée.');
                $('#loginMsg').text('');
                setAuthenticatedState(false);
            },
            error: function (xhr) {
                const message = handleAjaxError(xhr, 'Impossible de se déconnecter.');
                $('#logoutMsg').text(message);
            }
        });
    }

    function registerUser(formData) {
        $.ajax({
            url: `${API_BASE}/user`,
            method: 'POST',
            data: formData,
            success: function () {
                $('#registerMsg').text('Compte créé. Vous pouvez vous connecter.');
                $('#registerForm')[0].reset();
            },
            error: function (xhr) {
                const message = handleAjaxError(xhr, 'Création impossible.');
                $('#registerMsg').text(message);
            }
        });
    }

    function updateUser(data) {
        const userId = getStoredUserId();
        if (!userId) {
            $('#updateMsg').text('Utilisateur inconnu. Veuillez vous reconnecter.');
            return;
        }

        $.ajax({
            url: `${API_BASE}/user/${userId}`,
            method: 'PATCH',
            data: JSON.stringify(data),
            contentType: 'application/json',
            headers: {
                'Authorization': 'Bearer ' + getStoredToken()
            },
            xhrFields: {
                withCredentials: true
            },
            success: function () {
                $('#updateMsg').text('Profil mis à jour.');
            },
            error: function (xhr) {
                const message = handleAjaxError(xhr, 'Mise à jour impossible.');
                $('#updateMsg').text(message);
            }
        });
    }

    function deleteUser() {
        const userId = getStoredUserId();
        if (!userId) {
            $('#deleteMsg').text('Utilisateur inconnu. Veuillez vous reconnecter.');
            return;
        }

        $.ajax({
            url: `${API_BASE}/user/${userId}`,
            method: 'DELETE',
            headers: {
                'Authorization': 'Bearer ' + getStoredToken()
            },
            xhrFields: {
                withCredentials: true
            },
            success: function () {
                $('#deleteMsg').text('Compte supprimé.');
                localStorage.removeItem('accessToken');
                localStorage.removeItem('userId');
                localStorage.removeItem('userEmail');
                setAuthenticatedState(false);
            },
            error: function (xhr) {
                const message = handleAjaxError(xhr, 'Suppression impossible.');
                $('#deleteMsg').text(message);
            }
        });
    }

    function sendInvitation(opponentId) {
        $('#onlinePlayersMsg').text('Création de la partie...');

        $.ajax({
            url: `${API_BASE}/game`,
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + getStoredToken(),
                'Content-Type': 'application/json',
            },
            data: JSON.stringify({ opponent_id: opponentId }),
            processData: false,
            xhrFields: {
                withCredentials: true,
            },
            success: function (creationResponse) {
                const createdGameId = creationResponse.data?.game?.id
                    || creationResponse.data?.game_id
                    || creationResponse.data?.id;

                if (!createdGameId) {
                    $('#onlinePlayersMsg').text('Partie créée mais identifiant introuvable.');
                    return;
                }

                const invitation = creationResponse.data?.invitation;
                if (!invitation) {
                    $('#onlinePlayersMsg').text('Partie créée mais aucune invitation enregistrée.');
                    return;
                }

                $('#onlinePlayersMsg').text('Invitation envoyée.');
                setCurrentGame(createdGameId);
                fetchInvitations();
            },
            error: function (xhr) {
                const message = handleAjaxError(xhr, 'Impossible de créer la partie.');
                $('#onlinePlayersMsg').text(message);
            }
        });
    }

    function acceptInvitation(invitationId) {
        $.ajax({
            url: `${API_BASE}/game/${invitationId}/accept`,
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + getStoredToken(),
            },
            xhrFields: {
                withCredentials: true,
            },
            success: function (response) {
                $('#invitationMsg').text('Invitation acceptée. Bonne partie !');
                const gameId = response.data?.game_id || response.data?.id || invitationId;
                setCurrentGame(gameId);
                fetchInvitations();
            },
            error: function (xhr) {
                const message = handleAjaxError(xhr, "Impossible d'accepter l'invitation.");
                $('#invitationMsg').text(message);
            }
        });
    }

    function fetchOnlinePlayers() {
        $.ajax({
            url: `${API_BASE}/users/online`,
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + getStoredToken(),
            },
            xhrFields: {
                withCredentials: true,
            },
            success: function (response) {
                const players = response.data || [];
                const list = $('#onlinePlayersList');
                list.empty();

                if (!players.length) {
                    list.append('<li>Aucun joueur en ligne pour le moment.</li>');
                    return;
                }

                let hasDisplayedSomeone = false;

                players.forEach(function (player) {
                    if (String(player.id) === String(getStoredUserId())) {
                        return;
                    }
                    hasDisplayedSomeone = true;
                    const listItem = $('<li>').addClass('inline-actions');
                    const name = player.pseudo || `${player.first_name || ''} ${player.last_name || ''}`.trim() || `Joueur #${player.id}`;
                    listItem.append($('<span>').text(name));
                    const inviteButton = $('<button type="button">').text('Inviter');
                    inviteButton.on('click', function () {
                        sendInvitation(player.id);
                    });
                    listItem.append(inviteButton);
                    list.append(listItem);
                });

                if (!hasDisplayedSomeone) {
                    list.append('<li>Vous êtes le seul joueur connecté pour le moment.</li>');
                }
            },
            error: function (xhr) {
                const message = handleAjaxError(xhr, 'Impossible de récupérer les joueurs en ligne.');
                $('#onlinePlayersMsg').text(message);
            }
        });
    }

    function fetchInvitations() {
        $.ajax({
            url: `${API_BASE}/game/invitations`,
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + getStoredToken(),
            },
            xhrFields: {
                withCredentials: true,
            },
            success: function (response) {
                const invitations = response.data || [];
                const list = $('#invitationList');
                list.empty();
                $('#invitationMsg').text('');

                if (!invitations.length) {
                    list.append('<li>Aucune invitation en attente.</li>');
                    return;
                }

                invitations.forEach(function (invitation) {
                    const listItem = $('<li>').addClass('inline-actions');
                    const from = invitation.from?.pseudo || invitation.from?.email || `Joueur #${invitation.from_id || invitation.id}`;
                    listItem.append($('<span>').text(`Invitation de ${from}`));
                    const targetGameId = invitation.game_id || invitation.id;
                    const acceptButton = $('<button type="button">').text('Accepter');
                    acceptButton.on('click', function () {
                        acceptInvitation(targetGameId);
                    });
                    listItem.append(acceptButton);
                    list.append(listItem);
                });
            },
            error: function (xhr) {
                const message = handleAjaxError(xhr, 'Impossible de récupérer les invitations.');
                $('#invitationMsg').text(message);
            }
        });
    }

    function resolveCellData(cell, index) {
        if (!cell) {
            return { letter: '', status: '' };
        }

        if (typeof cell === 'string') {
            return { letter: cell[index] || cell, status: '' };
        }

        if (Array.isArray(cell)) {
            return resolveCellData(cell[index], index);
        }

        if (typeof cell === 'object') {
            const letter = cell.letter || cell.value || cell.char || '';
            const status = cell.status || cell.state || '';
            return { letter, status };
        }

        return { letter: '', status: '' };
    }

    function renderGameGrid(boardData) {
        const rows = 8;
        const columns = 6;
        const grid = $('#motusGrid');
        grid.empty();

        const rowsData = boardData || [];

        for (let rowIndex = 0; rowIndex < rows; rowIndex++) {
            const rowElement = $('<div>').addClass('motus-row');
            const rowData = rowsData[rowIndex] || { letters: [], statuses: [] };

            for (let colIndex = 0; colIndex < columns; colIndex++) {
                const cellElement = $('<span>').addClass('motus-cell');
                let letter = '';
                let status = '';

                if (typeof rowData === 'string') {
                    letter = rowData[colIndex] || '';
                } else if (Array.isArray(rowData)) {
                    const data = resolveCellData(rowData[colIndex], colIndex);
                    letter = data.letter || '';
                    status = data.status || '';
                } else if (typeof rowData === 'object') {
                    if (Array.isArray(rowData.letters)) {
                        letter = rowData.letters[colIndex] || '';
                    }
                    if (Array.isArray(rowData.statuses)) {
                        status = rowData.statuses[colIndex] || '';
                    }
                    if (!letter && Array.isArray(rowData.cells)) {
                        const data = resolveCellData(rowData.cells[colIndex], colIndex);
                        letter = data.letter || '';
                        status = data.status || '';
                    }
                }

                switch (status) {
                    case 'correct':
                    case 'good':
                    case 'ok':
                        cellElement.addClass('cell-correct');
                        break;
                    case 'present':
                    case 'partial':
                    case 'misplaced':
                        cellElement.addClass('cell-present');
                        break;
                    case 'absent':
                    case 'wrong':
                    case 'ko':
                        cellElement.addClass('cell-absent');
                        break;
                }

                cellElement.text((letter || '').toString().toUpperCase().charAt(0));
                rowElement.append(cellElement);
            }

            grid.append(rowElement);
        }
    }

    function stopGuessTimer() {
        if (guessTimer) {
            clearInterval(guessTimer);
            guessTimer = null;
        }
        guessTimeLeft = 0;
        $('#guessTimer').text('');
        $('#guessSubmit').prop('disabled', false);
    }

    function updateTimerDisplay() {
        if (guessTimeLeft > 0) {
            $('#guessTimer').text(`Temps restant : ${guessTimeLeft} seconde${guessTimeLeft > 1 ? 's' : ''}.`);
        }
    }

    function startGuessTimer() {
        stopGuessTimer();
        guessTimeLeft = TURN_TIMER_DURATION;
        updateTimerDisplay();
        $('#guessSubmit').prop('disabled', false);

        guessTimer = setInterval(function () {
            guessTimeLeft -= 1;
            if (guessTimeLeft <= 0) {
                stopGuessTimer();
                $('#guessTimer').text('Temps écoulé !');
                $('#guessSubmit').prop('disabled', true);
            } else {
                updateTimerDisplay();
            }
        }, 1000);
    }

    function updateTurnInfo(game) {
        const myId = getStoredUserId();
        const isFinished = game.status === 'finished' || game.status === 'won' || game.status === 'lost';
        const currentPlayerId = String(game.current_player_id || game.turn_player_id || '');
        const isMyTurn = myId && String(myId) === currentPlayerId;

        if (isFinished) {
            $('#turnInfo').text('La partie est terminée.');
            $('#guessForm').prop('hidden', true);
            stopGuessTimer();
            currentTurnKey = null;
            return;
        }

        const boardSource = Array.isArray(game.board) ? game.board : Array.isArray(game.grid) ? game.grid : Array.isArray(game.rows) ? game.rows : [];
        const attemptCounter = Array.isArray(boardSource) ? boardSource.filter(Boolean).length : '';
        const turnIdentifier = `${game.id || ''}:${firstNonNull(game.turn, game.turn_number, game.round, game.current_turn, game.played_turns, attemptCounter)}:${currentPlayerId}`;

        if (isMyTurn) {
            $('#turnInfo').text('À vous de jouer !');
            $('#guessForm').prop('hidden', false);
            $('#guessSubmit').prop('disabled', false);
            if (currentTurnKey !== turnIdentifier || guessTimeLeft === 0) {
                startGuessTimer();
            }
        } else if (currentPlayerId) {
            $('#turnInfo').text(`Tour du joueur #${currentPlayerId}.`);
            $('#guessForm').prop('hidden', true);
            stopGuessTimer();
        } else {
            $('#turnInfo').text('En attente du prochain tour...');
            $('#guessForm').prop('hidden', true);
            stopGuessTimer();
        }

        currentTurnKey = turnIdentifier;
    }

    function updateVictoryMessage(game) {
        let message = '';
        const myId = getStoredUserId();
        if (game.status === 'won') {
            if (game.winner_id && String(game.winner_id) === String(myId)) {
                message = 'Victoire ! 🎉';
            } else if (game.winner_id) {
                message = `Défaite. Le joueur #${game.winner_id} a trouvé le mot.`;
            } else {
                message = 'Victoire annoncée !';
            }
        } else if (game.status === 'lost') {
            message = 'Défaite. Le mot n’a pas été trouvé.';
        } else if (game.status === 'finished' && game.result) {
            message = game.result.message || '';
        }

        $('#victoryMessage').text(message);
    }

    function updateGameState(game) {
        if (!game) {
            $('#gameStatus').text('Aucune partie active.');
            $('#turnInfo').text('');
            $('#victoryMessage').text('');
            $('#guessForm').prop('hidden', true);
            renderGameGrid([]);
            stopGuessTimer();
            currentTurnKey = null;
            return;
        }

        $('#gameStatus').text(game.description || game.state || `Partie #${game.id}`);
        renderGameGrid(game.board || game.grid || game.rows || []);
        updateTurnInfo(game);
        updateVictoryMessage(game);

        if (game.status === 'won' || game.status === 'lost' || game.status === 'finished') {
            stopGuessTimer();
            currentTurnKey = null;
        }
    }

    function fetchGameState(gameId) {
        if (!gameId) {
            updateGameState(null);
            return;
        }

        $.ajax({
            url: `${API_BASE}/game/${gameId}`,
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + getStoredToken(),
            },
            xhrFields: {
                withCredentials: true,
            },
            success: function (response) {
                const game = response.data?.game || response.data || null;
                updateGameState(game);
            },
            error: function (xhr) {
                const message = handleAjaxError(xhr, 'Impossible de récupérer la partie.');
                $('#gameStatus').text(message);
            }
        });
    }

    function submitGuess(word) {
        if (!currentGameId) {
            $('#guessMsg').text('Aucune partie en cours.');
            return;
        }

        $.ajax({
            url: `${API_BASE}/game/${currentGameId}/guess`,
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + getStoredToken(),
            },
            data: { word: word },
            xhrFields: {
                withCredentials: true,
            },
            success: function (response) {
                $('#guessMsg').text('Mot envoyé.');
                $('#guessInput').val('');
                stopGuessTimer();
                fetchGameState(currentGameId);
            },
            error: function (xhr) {
                const message = handleAjaxError(xhr, 'Le mot n’a pas pu être envoyé.');
                $('#guessMsg').text(message);
            }
        });
    }

    function setCurrentGame(gameId) {
        currentGameId = gameId || null;
        if (currentGameId) {
            $('#gameSection').prop('hidden', false);
            fetchGameState(currentGameId);
            if (gameInterval) {
                clearInterval(gameInterval);
            }
            gameInterval = setInterval(function () {
                fetchGameState(currentGameId);
            }, GAME_REFRESH);
        } else {
            updateGameState(null);
            if (gameInterval) {
                clearInterval(gameInterval);
                gameInterval = null;
            }
            currentTurnKey = null;
        }
    }

    function fetchCurrentGame() {
        $.ajax({
            url: `${API_BASE}/game/current`,
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + getStoredToken(),
            },
            xhrFields: {
                withCredentials: true,
            },
            success: function (response) {
                const game = response.data?.game || response.data || null;
                if (game?.id) {
                    setCurrentGame(game.id);
                } else {
                    setCurrentGame(null);
                }
            },
            error: function () {
                setCurrentGame(null);
            }
        });
    }

    function enableGameplayFeatures() {
        fetchOnlinePlayers();
        fetchInvitations();
        fetchCurrentGame();

        if (onlinePlayersInterval) {
            clearInterval(onlinePlayersInterval);
        }
        if (invitationsInterval) {
            clearInterval(invitationsInterval);
        }

        onlinePlayersInterval = setInterval(fetchOnlinePlayers, ONLINE_PLAYERS_REFRESH);
        invitationsInterval = setInterval(fetchInvitations, INVITATIONS_REFRESH);
    }

    function disableGameplayFeatures() {
        $('#onlinePlayersList').empty();
        $('#onlinePlayersMsg').text('');
        $('#invitationList').empty();
        $('#invitationMsg').text('');
        setCurrentGame(null);

        if (onlinePlayersInterval) {
            clearInterval(onlinePlayersInterval);
            onlinePlayersInterval = null;
        }

        if (invitationsInterval) {
            clearInterval(invitationsInterval);
            invitationsInterval = null;
        }
    }

    $('#loginForm').on('submit', function (event) {
        event.preventDefault();
        const email = $('#loginEmail').val().trim();
        const password = $('#loginPassword').val();
        $('#loginMsg').text('Connexion en cours...');
        authenticate(email, password);
    });

    $('#logoutForm').on('submit', function (event) {
        event.preventDefault();
        $('#logoutMsg').text('Déconnexion en cours...');
        deauthenticate();
    });

    $('#registerForm').on('submit', function (event) {
        event.preventDefault();
        const formData = $(this).serialize();
        $('#registerMsg').text('Création du compte...');
        registerUser(formData);
    });

    $('#updateForm').on('submit', function (event) {
        event.preventDefault();
        const pseudo = $('#updatePseudo').val().trim();
        const gender = $('#updateGender').val();
        const avatar = $('#updateAvatar').val().trim();

        const toUpdate = {};
        if (pseudo) { toUpdate.pseudo = pseudo; }
        if (gender) { toUpdate.gender = gender; }
        if (avatar) { toUpdate.avatar = avatar; }

        if (Object.keys(toUpdate).length === 0) {
            $('#updateMsg').text('Aucune modification à envoyer.');
            return;
        }

        $('#updateMsg').text('Mise à jour en cours...');
        updateUser(toUpdate);
    });

    $('#deleteForm').on('submit', function (event) {
        event.preventDefault();
        $('#deleteMsg').text('Suppression en cours...');
        deleteUser();
    });

    $('#guessForm').on('submit', function (event) {
        event.preventDefault();
        const guess = $('#guessInput').val().trim();
        if (!/^[A-Za-zÀ-ÖØ-öø-ÿ]{6}$/.test(guess)) {
            $('#guessMsg').text('Le mot doit contenir exactement 6 lettres.');
            return;
        }

        $('#guessMsg').text('Envoi en cours...');
        submitGuess(guess.toLowerCase());
    });

    $(document).ready(function () {
        const storedToken = getStoredToken();
        const storedUserId = getStoredUserId();
        const storedEmail = localStorage.getItem('userEmail');

        if (storedToken && storedUserId) {
            setAuthenticatedState(true, { userId: storedUserId, email: storedEmail || '' });
        } else {
            setAuthenticatedState(false);
        }
    });
</script>
</body>
</html>
