# FredRedact

FredRedact est une application de gestion des stages et de rédaction de rapports. Elle comprend une API Laravel, une interface React/Vite et un service Python/Flask pour l’assistance IA, l’extraction de documents et les exports.

## Fonctionnalités

- Comptes étudiants et administrateurs, authentification, profil et réinitialisation de mot de passe.
- Gestion des stages, rapports, chapitres, sections et versions.
- Assistant Gemini pour le dialogue, les plans, la rédaction et la recherche documentaire.
- Import de PDF, Word et images; extraction de texte et OCR.
- Export des rapports en PDF et Word.
- Offres Free, Pro et Premium, crédits et consommation de tokens.
- Paiement Mobile Money via CinetPay (configuration nécessaire).

## Prérequis

- Windows avec XAMPP (Apache/PHP et MySQL), ou un environnement compatible.
- PHP 8.2 ou ultérieur et Composer.
- Node.js 18 ou ultérieur et npm.
- Python 3.11 ou ultérieur.
- Une base MySQL/MariaDB ou SQLite.
- Une clé API Gemini pour activer les fonctions IA.
- Tesseract OCR, facultatif, pour extraire le texte des images.

## Installation locale sous Windows

Depuis PowerShell, clonez le dépôt et installez les dépendances PHP :

```powershell
git clone https://github.com/Fred-NANGUE/FredRedact.git
Set-Location FredRedact
composer install
Copy-Item .env.example .env
php artisan key:generate
```

### Base de données

Choisissez **une seule** méthode :

1. **Base neuve avec le dump XAMPP** : dans phpMyAdmin, importez `fredredact_database.sql` dans une base vide. Configurez ensuite `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=fredredact`, `DB_USERNAME` et `DB_PASSWORD` dans `.env`. Ne restaurez jamais ce dump par-dessus une base contenant des données.
2. **Création par migrations** : configurez la connexion MySQL dans `.env`, puis exécutez `php artisan migrate --seed`. Pour SQLite, créez `database\database.sqlite`, configurez `DB_CONNECTION=sqlite` et lancez la même commande.

Pour une ancienne base XAMPP avec des données à conserver, consultez les scripts `xampp_fix_users_name.sql` et `xampp_fix_internships.sql`. Sauvegardez la base avant toute modification; n’importez pas le dump de base neuve dans cette base.

### Identifiants de services

Les fichiers `.env` locaux ne doivent jamais être commités.

- **Google** (facultatif) : créez un identifiant OAuth de type application Web, autorisez les origines utilisées (par exemple `http://localhost:5173` et `http://127.0.0.1:5173`), puis renseignez le même `GOOGLE_CLIENT_ID` dans `.env` et `VITE_GOOGLE_CLIENT_ID` dans `frontend\.env`.
- **E-mail** (facultatif en développement) : configurez `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` et `MAIL_FROM_ADDRESS` dans `.env`. Pour Gmail, utilisez un mot de passe d’application; ne le mettez ni dans le code ni dans Git.
- **Paiement** (facultatif) : renseignez `CINETPAY_API_KEY` et `CINETPAY_SITE_ID`. Les notifications de paiement requièrent une URL publique HTTPS configurée dans `APP_URL`; un serveur local seul ne peut pas recevoir le webhook.

### Démarrer le service IA

Dans `ai-service\.env`, créez une configuration à partir de l’exemple :

```powershell
Copy-Item ai-service\.env.example ai-service\.env
python -m venv ai-service\.venv
ai-service\.venv\Scripts\python -m pip install -r ai-service\requirements.txt
```

Renseignez `GEMINI_API_KEY` dans `ai-service\.env`. Configurez aussi un secret aléatoire fort `AI_SERVICE_KEY` dans **les deux** fichiers `.env` (racine et `ai-service\.env`); les valeurs doivent être identiques. Le modèle par défaut est `gemini-3.5-flash-lite`.

Démarrez Flask dans un terminal :

```powershell
ai-service\.venv\Scripts\python ai-service\run.py
```

Le service écoute sur `127.0.0.1:5001` par défaut. Ne l’exposez pas directement sur Internet.

### Démarrer Laravel et le frontend

Dans deux autres terminaux, depuis la racine du projet :

```powershell
php artisan serve --host 127.0.0.1 --port 8000
```

```powershell
Copy-Item frontend\.env.example frontend\.env
npm --prefix frontend install
npm --prefix frontend run dev
```

L’interface est accessible à `http://localhost:5173`. Par défaut, elle appelle l’API Laravel sur `http://127.0.0.1:8000/api`; ajustez `VITE_API_URL` dans `frontend\.env` si nécessaire. Après toute modification de cet environnement, redémarrez Vite.

Pour créer un administrateur, utilisez :

```powershell
php artisan users:create-admin admin@example.com --name="Administrateur"
```

## OCR et fichiers

L’import PDF et Word, l’extraction et les exports sont assurés par le service IA. L’OCR des images requiert Tesseract installé sur la machine et accessible dans le `PATH`. Les dépendances installées, les fichiers `.env`, les caches et les documents importés ne doivent pas être ajoutés au dépôt.

## Vérifications

```powershell
php artisan test
npm --prefix frontend run build
Set-Location ai-service
python -m unittest discover -s tests
```

## Fichiers SQL

- `fredredact_database.sql` : schéma et données initiales pour créer une base FredRedact neuve.
- `xampp_fix_users_name.sql` et `xampp_fix_internships.sql` : corrections ciblées pour certains anciens schémas.
- `test.sql` : exemple de rapport de test pour le stage AGB de l’utilisateur `id=1`; à importer seulement si ce compte et ce stage existent dans la base sélectionnée.

Vérifiez toujours la base active et faites une sauvegarde avant d’exécuter un script SQL.
