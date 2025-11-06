# Schéma du mécanisme d'authentification

```
+----------------+                             +-----------------------+
| Navigateur     | 1. POST /api/login          | API                   |
| (client jQuery)|---------------------------->| - Vérifie email/mot de |
|                | email, password             |   passe               |
|                |                             | - Retourne access      |
|                |                             |   token (JWT)         |
|                |                             | - Stocke le refresh    |
|                |                             |   token en base        |
+----------------+                             +-----------------------+
        |                                                   |
        | 2. Cookie HttpOnly refresh_token <----------------+
        | 3. Stockage localStorage accessToken
        v
+----------------+                             +-----------------------+
| Appel protégé  | 4. GET/POST/PATCH/...       | API                   |
| (AJAX)         | Authorization: Bearer JWT   | - Vérifie la signature |
|                |---------------------------->|   et l'expiration      |
|                |                             | - Autorise ou refuse   |
+----------------+                             +-----------------------+
        |                                                   |
        | 5. 401 Unauthorized ?                             |
        |------------------------------Non------------------+
        |                                                   |
        +---------------------------Oui---------------------->
        | 6. POST /api/token/refresh                         |
        |    (cookie refresh_token)                          |
        |----------------------------->+---------------------+
        |                              | API                 |
        |                              | - Vérifie le        |
        |                              |   refresh token     |
        |                              | - Émet un nouvel    |
        |                              |   access token      |
        |<-----------------------------+---------------------+
        | 7. Mise à jour du localStorage avec le nouveau JWT |
        v                                                   |
+----------------+                             +-----------------------+
| Déconnexion    | 8. POST /api/logout         | API                   |
| (AJAX)         | Authorization: Bearer JWT   | - Révoque le refresh   |
|                |---------------------------->|   token associé        |
|                |                             | - Demande au client de |
|                |                             |   supprimer le JWT     |
+----------------+                             +-----------------------+
```

L'access token est utilisé pour authentifier rapidement chaque requête protégée, tandis que le refresh token, conservé côté serveur et envoyé au client via un cookie HttpOnly, permet d'obtenir un nouveau jeton d'accès sans redemander les identifiants tant qu'il n'est pas expiré ou révoqué.
