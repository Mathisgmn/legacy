<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client jQuery</title>
    <link rel="stylesheet" href="/assets/css/pico.min.css">
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

        if (isAuthenticated && sessionData) {
            $('#sessionInfo').text(`Connecté en tant qu'utilisateur #${sessionData.userId} (${sessionData.email}).`);
        } else if (isAuthenticated && getStoredUserId()) {
            $('#sessionInfo').text(`Connecté en tant qu'utilisateur #${getStoredUserId()}.`);
        } else {
            $('#sessionInfo').text('Non authentifié.');
        }
    }

    function handleAjaxError(xhr, fallbackMessage) {
        const message = xhr.responseJSON?.message || fallbackMessage;
        return message;
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
