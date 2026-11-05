# Interface React - Membre 6 (abonnements, tokens, administration)

Application React (Vite) qui consomme l'API Laravel du Membre 6.

## Lancer

Prérequis : **Node.js 18 ou plus** (`node -v`). Sinon, installez la version LTS depuis nodejs.org.

```
cd frontend
npm install
copy .env.example .env
npm run dev
```

Ouvrez **http://localhost:5173**. Dans un autre terminal, gardez le serveur Laravel actif :
`php artisan serve` (adresse par défaut http://127.0.0.1:8000).

## Connexion

- Collez un jeton créé avec `php artisan tinker` (`User::find(2)->createToken('t')->plainTextToken` pour l'étudiant, `User::find(1)...` pour l'admin), ou
- utilisez email + mot de passe si la route de connexion du Membre 1 renvoie un jeton (réglez `VITE_LOGIN_PATH` dans `.env`).

Le rôle est détecté automatiquement : un administrateur voit en plus le tableau de bord, la gestion des offres, les abonnements et les tokens.

## Écrans

| Écran | Qui | Contenu |
|---|---|---|
| Offres | tous | cartes Free / Pro / Premium, souscription |
| Mon compte | tous | abonnement, jauge de tokens, historique filtrable, consommation par rapport, annulation |
| Tableau de bord | admin | abonnés, revenus, tokens, graphiques, top consommateurs |
| Gestion des offres | admin | créer, modifier, activer/désactiver, supprimer |
| Abonnements | admin | filtres, activer, suspendre, réactiver, annuler, attribuer une offre |
| Tokens | admin | solde d'un utilisateur, recharge, ajustement, historique |

## Réglages (`.env` du dossier frontend)

- `VITE_API_URL` : adresse de l'API (défaut http://127.0.0.1:8000/api)
- `VITE_LOGIN_PATH` : route de connexion du Membre 1 (défaut `/login`)
- `VITE_CURRENCY` : symbole de la devise affichée (défaut €)

Le CORS est déjà autorisé par défaut sur `/api/*` dans Laravel : rien à configurer.

## Intégration

Si l'équipe a déjà un projet React commun, copiez le dossier `src/pages` (sauf `Login.jsx` si le Membre 1 fournit le sien), `src/api.js`, `src/hooks.js`, `src/ui.jsx` et `src/styles.css`, puis montez `Plans`, `Account` et les pages `admin/*` dans votre routeur.
