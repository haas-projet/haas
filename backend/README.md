# Base commune du backend HAAS

Cette première étape crée les dossiers partagés pour les trois développeurs. Les fichiers `.gitkeep` permettent de conserver les dossiers vides dans Git, afin que chaque clone reçoive la même arborescence.

**État : arborescence uniquement.** Laravel et ses dépendances ne sont pas installés ; aucun endpoint ni migration métier n'est implémenté. `composer install`, `php artisan` et les tests applicatifs ne sont pas encore disponibles. Le lot B01 reste à réaliser.

## Arborescence

```text
backend/
├── AGENTS.md
├── README.md
├── app/
│   ├── Data/                   # DTO typés, sans dépendance HTTP
│   ├── Enums/                  # États et valeurs métier
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/V1/         # Contrôleurs des endpoints métier
│   │   │   └── Auth/           # Authentification par session
│   │   ├── Middleware/
│   │   ├── Requests/           # Validation des entrées
│   │   └── Resources/          # Réponses API autorisées
│   ├── Models/                # Modèles Eloquent et relations
│   ├── Policies/              # Droits par ressource
│   ├── Providers/
│   ├── Queries/               # Lectures avec visibilité contrôlée
│   ├── Services/              # Cas métier et transactions
│   └── Support/
│       ├── Audit/
│       ├── Http/
│       └── Idempotency/
├── bootstrap/cache/
├── config/
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── public/
├── routes/api/
└── tests/
    ├── Architecture/
    ├── Feature/
    ├── Integration/
    └── Unit/
```

Créer les sous-dossiers métier au moment de leur utilisation, par exemple `Services/Projects/`, `Requests/HelpOffers/` ou `Controllers/Api/V1/Capsules/`. Les Models et Policies restent aux emplacements conventionnels. Ne pas créer un dossier applicatif par développeur ni trois installations Laravel.

## Responsabilités

| Branche | Responsable | Domaine |
|---|---|---|
| `backend/socle-auth` | `ousseynoufayeisidk-sys` | Socle, identité, services partagés et intégration |
| `backend/communaute-entraide` | `LamineGL` | Demandes, projets, annuaire et coups de main |
| `backend/capsules-laboratoire` | `mdev44-code` | Capsules, cas et laboratoire |

Lire [le plan de travail à trois](../docs/execution/BACKEND_A_TROIS.md) pour les fichiers réservés et dépendances. Les futures routes séparées `routes/api/identity.php`, `community.php` et `capsules-lab.php` seront enregistrées une seule fois dans le socle commun ; ces fichiers ne sont pas encore créés.

## Prochaine étape

Le responsable du socle termine l'inventaire S01/S02 et initialise Laravel dans un dossier temporaire, puis fusionne les fichiers attendus dans cette arborescence après inspection. Préserver `AGENTS.md`, cette documentation et les contributions des autres branches. Choisir et verrouiller les versions compatibles, configurer PostgreSQL et vérifier le démarrage selon B01.

Ne pas démarrer des installations Laravel indépendantes sur les autres branches. Après intégration du socle validé, chacun récupère `main` avant de développer les fonctionnalités qui en dépendent. Backend P0 et GO_FRONTEND humain précèdent toute application React.
