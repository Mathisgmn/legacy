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

    <form id="loginForm">
        <fieldset role="group">
            <input type="email" id="email" name="email" placeholder="Email" required />
            <input type="password" id="password" name="password" placeholder="Mot de passe" required />
            <button type="submit">Se connecter</button>
        </fieldset>
        <small id="loginMsg" aria-live="polite"></small>
    </form>
</main>

<script>
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

    function authenticate(email, password) {
        $.ajax({
            url: 'http://localhost:8000/api/login',
            method: 'POST',
            data: {email: email, password: password},
            success: function (response) {
                const accessToken = response.data.accessToken;
                localStorage.setItem('accessToken', accessToken);
                $('#loginMsg').text('Connexion réussie.');
            },
            error: function () {
                $('#loginMsg').text('Échec de la connexion.');
            }
        });
    }

    function deauthenticate() {
        $.ajax({
            url: 'http://localhost:8000/api/logout',
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('accessToken'),
            },
            success: function (response) {
                localStorage.removeItem('accessToken');
            },
            error: function () {}
        });
    }

    function reauthenticate(callback) {
        $.ajax({
            url: 'http://localhost:8000/api/token/refresh',
            method: 'POST',
            success: function (response) {
                const accessToken = response.data.accessToken;
                localStorage.setItem('accessToken', accessToken);
                if (callback) callback(response.data.accessToken);
            },
            error: function () {}
        });
    }

    function endpointList() {
        $.ajax({
            url: 'http://localhost:8000/api/user',
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('accessToken'),
            },
            success: function (response) {},
            error: function () {}
        });
    }

    function endpointGet(id) {
        $.ajax({
            url: 'http://localhost:8000/api/user' + '/' + id,
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('accessToken'),
            },
            success: function (response) {},
            error: function () {}
        });
    }

    function endpointCreate(data) {
        $.ajax({
            url: 'http://localhost:8000/api/user',
            method: 'POST',
            data: data,
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('accessToken'),
            },
            success: function (response) {},
            error: function () {}
        });
    }

    function endpointUpdate(id, data) {
        $.ajax({
            url: 'http://localhost:8000/api/user' + '/' + id,
            method: 'PATCH',
            data: JSON.stringify(data),
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('accessToken'),
                'Content-Type': 'application/json'
            },
            success: function (response) {},
            error: function () {}
        });
    }

    function endpointReplace(id) {}

    function endpointDelete(id) {
        $.ajax({
            url: 'http://localhost:8000/api/user' + '/' + id,
            method: 'DELETE',
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('accessToken'),
            },
            success: function (response) {},
            error: function () {}
        });
    }

    const action = 0;
    switch (action) {
        case 1: authenticate("lucas.morel@example.com", "password"); break;
        case 2: deauthenticate(); break;
        case 3: reauthenticate(); break;
        case 4: endpointList(); break;
        case 5: endpointGet(1); break;
        case 6: endpointCreate(dataSample); break;
        case 7: endpointUpdate(1, partialDataSample); break;
        case 8: break;
        case 9: endpointDelete(6); break;
        default: break;
    }

    $('#loginForm').on('submit', function (e) {
        e.preventDefault();
        const email = $('#email').val().trim();
        const password = $('#password').val();
        $('#loginMsg').text('Connexion en cours...');
        authenticate(email, password);
    });
</script>
</body>
</html>
