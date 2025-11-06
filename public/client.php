<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client jQuery</title>
    <link rel="stylesheet" href="/assets/css/pico.min.css">
    <script src="/assets/js/jquery-3.7.1.min.js"></script>
    <style>
        pre {
            background-color: var(--muted-border-color);
            padding: 1rem;
            border-radius: .5rem;
            max-height: 20rem;
            overflow: auto;
        }

        [aria-live] {
            min-height: 2rem;
        }
    </style>
</head>
<body>
<main class="container">
    <h1>Client jQuery</h1>
    <p>Utilisez cette page pour dialoguer avec l'API : authentification, création, mise à jour ou suppression de compte.</p>

    <article id="statusPanel" class="contrast" aria-live="polite"></article>

    <section id="loginSection">
        <h2>Authentification</h2>
        <form id="loginForm">
            <fieldset role="group">
                <input type="email" id="loginEmail" name="email" placeholder="Email" required>
                <input type="password" id="loginPassword" name="password" placeholder="Mot de passe" required>
                <button type="submit">Se connecter</button>
            </fieldset>
        </form>
        <form id="logoutForm">
            <button type="submit" class="secondary">Se déconnecter</button>
        </form>
    </section>

    <section>
        <h2>Inscription</h2>
        <form id="registerForm">
            <div class="grid">
                <input type="text" name="first_name" placeholder="Prénom" required>
                <input type="text" name="last_name" placeholder="Nom" required>
            </div>
            <div class="grid">
                <input type="text" name="pseudo" placeholder="Pseudo" required>
                <input type="date" name="birth_date" placeholder="Date de naissance" required>
            </div>
            <div class="grid">
                <select name="gender" required>
                    <option value="">Genre</option>
                    <option value="f">Féminin</option>
                    <option value="m">Masculin</option>
                    <option value="o">Autre</option>
                </select>
                <input type="text" name="avatar" placeholder="Avatar (nom de fichier)">
            </div>
            <div class="grid">
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Mot de passe" required>
            </div>
            <button type="submit" class="contrast">Créer le compte</button>
        </form>
    </section>

    <section>
        <h2>Mettre à jour mon compte</h2>
        <form id="updateForm">
            <div class="grid">
                <input type="text" id="updatePseudo" name="pseudo" placeholder="Nouveau pseudo">
                <select id="updateGender" name="gender">
                    <option value="">Genre (laisser vide pour conserver)</option>
                    <option value="f">Féminin</option>
                    <option value="m">Masculin</option>
                    <option value="o">Autre</option>
                </select>
            </div>
            <input type="text" id="updateAvatar" name="avatar" placeholder="Avatar (laisser vide pour conserver)">
            <button type="submit">Enregistrer les modifications</button>
        </form>
    </section>

    <section>
        <h2>Supprimer mon compte</h2>
        <form id="deleteForm">
            <button type="submit" class="outline" data-type="danger">Supprimer définitivement</button>
        </form>
    </section>

    <section>
        <h2>Données utilisateur</h2>
        <pre id="userInfo">Aucune donnée chargée.</pre>
    </section>
</main>

<script>
    const API_BASE_URL = 'http://localhost:8000/api';
    let currentUserId = null;

    function base64UrlDecode(input) {
        const base64 = input.replace(/-/g, '+').replace(/_/g, '/');
        const padded = base64.padEnd(base64.length + (4 - base64.length % 4) % 4, '=');
        return atob(padded);
    }

    function decodeJwt(token) {
        try {
            const parts = token.split('.');
            if (parts.length !== 3) {
                return null;
            }
            const payload = base64UrlDecode(parts[1]);
            const json = decodeURIComponent(Array.from(payload).map(c => '%' + c.charCodeAt(0).toString(16).padStart(2, '0')).join(''));
            return JSON.parse(json);
        } catch (error) {
            return null;
        }
    }

    function setStatus(message, type = 'info') {
        const panel = $('#statusPanel');
        panel.removeClass('error success info');
        panel.addClass(type);
        panel.text(message);
    }

    function setAccessToken(token) {
        if (token) {
            localStorage.setItem('accessToken', token);
            const payload = decodeJwt(token);
            currentUserId = payload && payload.user_id ? parseInt(payload.user_id, 10) : null;
        } else {
            localStorage.removeItem('accessToken');
            currentUserId = null;
        }
        toggleAuthenticatedUi();
    }

    function toggleAuthenticatedUi() {
        const isAuthenticated = Boolean(currentUserId);
        $('#loginForm').prop('hidden', isAuthenticated);
        $('#logoutForm').prop('hidden', !isAuthenticated);
        $('#updateForm').prop('hidden', !isAuthenticated);
        $('#deleteForm').prop('hidden', !isAuthenticated);
        $('#registerForm').prop('hidden', isAuthenticated);
        if (!isAuthenticated) {
            $('#userInfo').text('Aucune donnée chargée.');
        }
    }

    function extractErrorMessage(jqXHR) {
        if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.message) {
            return jqXHR.responseJSON.message;
        }
        return jqXHR && jqXHR.status ? `Erreur ${jqXHR.status}` : 'Une erreur est survenue';
    }

    function refreshToken() {
        const deferred = $.Deferred();
        $.ajax({
            url: `${API_BASE_URL}/token/refresh`,
            method: 'POST',
            dataType: 'json',
            xhrFields: { withCredentials: true }
        }).done(function (response) {
            const newToken = response?.data?.accessToken || null;
            if (newToken) {
                setAccessToken(newToken);
                deferred.resolve(newToken);
            } else {
                setAccessToken(null);
                deferred.reject(response);
            }
        }).fail(function (xhr) {
            setAccessToken(null);
            deferred.reject(xhr);
        });
        return deferred.promise();
    }

    function callApi(endpoint, options = {}) {
        const deferred = $.Deferred();

        function execute(retry) {
            const ajaxOptions = $.extend(true, {
                url: `${API_BASE_URL}${endpoint}`,
                method: 'GET',
                dataType: 'json',
                headers: {},
                xhrFields: { withCredentials: true }
            }, options);

            const token = localStorage.getItem('accessToken');
            if (token) {
                ajaxOptions.headers.Authorization = `Bearer ${token}`;
            }

            $.ajax(ajaxOptions).done(function (data, textStatus, jqXHR) {
                deferred.resolve(data, textStatus, jqXHR);
            }).fail(function (jqXHR) {
                if (jqXHR.status === 401 && retry && token) {
                    refreshToken().done(function () {
                        execute(false);
                    }).fail(function () {
                        deferred.reject(jqXHR);
                    });
                } else {
                    deferred.reject(jqXHR);
                }
            });
        }

        execute(true);
        return deferred.promise();
    }

    function displayUserInfo(data) {
        const container = $('#userInfo');
        if (!data) {
            container.text('Aucune donnée chargée.');
            return;
        }
        const user = Array.isArray(data) ? data[0] : data;
        container.text(JSON.stringify(user, null, 2));
    }

    function fetchCurrentUser(silent = false) {
        if (!currentUserId) {
            return $.Deferred().reject().promise();
        }
        return callApi(`/user/${currentUserId}`, { method: 'GET' }).done(function (response) {
            displayUserInfo(response.data);
            if (!silent) {
                setStatus(response.message || 'Données utilisateur chargées.', 'success');
            }
        }).fail(function (xhr) {
            if (!silent) {
                setStatus(extractErrorMessage(xhr), 'error');
            }
        });
    }

    $(function () {
        toggleAuthenticatedUi();

        const storedToken = localStorage.getItem('accessToken');
        if (storedToken) {
            setAccessToken(storedToken);
            fetchCurrentUser(true).fail(function () {
                setAccessToken(null);
            });
        }

        $('#loginForm').on('submit', function (event) {
            event.preventDefault();
            setStatus('Connexion en cours…');
            $.ajax({
                url: `${API_BASE_URL}/login`,
                method: 'POST',
                dataType: 'json',
                data: $(this).serialize(),
                xhrFields: { withCredentials: true }
            }).done(function (response) {
                const token = response?.data?.accessToken || null;
                const userId = response?.data?.userId || null;
                if (token) {
                    setAccessToken(token);
                    if (userId) {
                        currentUserId = parseInt(userId, 10);
                    }
                    setStatus(response.message || 'Connexion réussie.', 'success');
                    fetchCurrentUser(true);
                } else {
                    setStatus('Réponse inattendue : access token manquant.', 'error');
                }
            }).fail(function (xhr) {
                setAccessToken(null);
                setStatus(extractErrorMessage(xhr), 'error');
            });
        });

        $('#logoutForm').on('submit', function (event) {
            event.preventDefault();
            if (!currentUserId) {
                setAccessToken(null);
                return;
            }
            setStatus('Déconnexion…');
            callApi('/logout', { method: 'POST' }).done(function (response) {
                setStatus(response.message || 'Déconnexion réussie.', 'success');
                setAccessToken(null);
            }).fail(function (xhr) {
                setStatus(extractErrorMessage(xhr), 'error');
            });
        });

        $('#registerForm').on('submit', function (event) {
            event.preventDefault();
            setStatus('Création du compte en cours…');
            callApi('/user', {
                method: 'POST',
                data: $(this).serialize()
            }).done(function (response) {
                setStatus(response.message || 'Compte créé avec succès.', 'success');
                const createdUser = Array.isArray(response.data) ? response.data[0] : response.data;
                if (createdUser) {
                    $('#loginEmail').val(createdUser.email || '');
                }
            }).fail(function (xhr) {
                setStatus(extractErrorMessage(xhr), 'error');
            });
        });

        $('#updateForm').on('submit', function (event) {
            event.preventDefault();
            if (!currentUserId) {
                setStatus('Vous devez être authentifié pour modifier votre compte.', 'error');
                return;
            }

            const payload = {};
            const pseudo = $('#updatePseudo').val().trim();
            const gender = $('#updateGender').val();
            const avatar = $('#updateAvatar').val().trim();

            if (pseudo) {
                payload.pseudo = pseudo;
            }
            if (gender) {
                payload.gender = gender;
            }
            if (avatar) {
                payload.avatar = avatar;
            }

            if (!Object.keys(payload).length) {
                setStatus('Renseignez au moins un champ avant de soumettre.', 'error');
                return;
            }

            setStatus('Mise à jour en cours…');
            callApi(`/user/${currentUserId}`, {
                method: 'PATCH',
                data: JSON.stringify(payload),
                contentType: 'application/json'
            }).done(function (response) {
                setStatus(response.message || 'Compte mis à jour.', 'success');
                displayUserInfo(response.data);
                $('#updateForm')[0].reset();
            }).fail(function (xhr) {
                setStatus(extractErrorMessage(xhr), 'error');
            });
        });

        $('#deleteForm').on('submit', function (event) {
            event.preventDefault();
            if (!currentUserId) {
                setStatus('Vous devez être authentifié pour supprimer votre compte.', 'error');
                return;
            }

            const confirmation = confirm('Cette action est irréversible. Confirmez-vous la suppression de votre compte ?');
            if (!confirmation) {
                return;
            }

            setStatus('Suppression du compte en cours…');
            callApi(`/user/${currentUserId}`, { method: 'DELETE' }).done(function (response) {
                setStatus(response.message || 'Compte supprimé.', 'success');
                setAccessToken(null);
            }).fail(function (xhr) {
                setStatus(extractErrorMessage(xhr), 'error');
            });
        });
    });
</script>
</body>
</html>
