# Base commune du backend HAAS

Cette première étape crée les dossiers partagés pour les trois développeurs. Les fichiers `.gitkeep` permettent de conserver les dossiers vides dans Git, afin que chaque clone reçoive la même arborescence.

**État : B01 intégré dans `main` par la PR #4.** Laravel 13.34.0, PHP 8.4 minimum, PostgreSQL, identifiants utilisateur UUID et fuseau UTC. `/up` vérifie le démarrage sans accès SQL. Les parcours d'inscription et de connexion restent à développer.

Suivre [les commandes d'installation et de test](../docs/COMMANDS.md). Consulter [les versions observées](../docs/VERSIONS.md) et [les preuves B01](../docs/quality/B01_BOOTSTRAP.md) : 12 tests et 22 assertions réussis, dont une intégration PostgreSQL réelle.

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

Lire [le plan de travail à trois](../docs/execution/BACKEND_A_TROIS.md) pour les fichiers réservés et dépendances. Les routes séparées `routes/api/identity.php`, `community.php` et `capsules-lab.php` sont enregistrées une seule fois sous `/api/v1`. Ces fichiers sont vides en attendant les lots métier.

## Prochaine étape

Le responsable du socle poursuit B02 (qualité PHP), B03 (CI), B04 (contrat HTTP), B05 (identité), puis l'authentification. Les deux autres développeurs peuvent coder les parties indépendantes de leur domaine sur cette base ; les PR dépendantes attendent leurs prérequis. Voir le démarrage parallèle dans le plan d'équipe. L'inventaire des services externes S01/S02 reste partiel.

Ne pas démarrer des installations Laravel indépendantes sur les autres branches. Après intégration du socle validé, chacun récupère `main` avant de développer les fonctionnalités qui en dépendent. Backend P0 et GO_FRONTEND humain précèdent toute application React.
