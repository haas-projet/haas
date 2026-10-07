# Backend HAAS

Le backend est un monolithe Laravel partagé par les trois développeurs. Les couches séparent validation HTTP, données typées, cas métier, droits et lectures. Les fichiers `.gitkeep` conservent les dossiers repères dans Git.

Laravel 13.34.0, PHP 8.4 minimum, PostgreSQL, identifiants utilisateur UUID et fuseau UTC. L'authentification par session Sanctum, les [courriels de compte](../docs/api/ACCOUNT_MAIL.md), `/api/v1/me` et les permissions sont intégrés dans `main`. `/up` vérifie le démarrage sans accès SQL. SMTP réel et réception complète restent à valider.

Suivre [les commandes d'installation et de test](../docs/COMMANDS.md), [les versions observées](../docs/VERSIONS.md) et [l'état des livraisons](../README.md). Les résultats datés sont dans [les preuves de qualité](../docs/quality/) ; le [suivi d'exécution](../docs/execution/PROGRESS.md) indique les lots reçus, partiels et bloqués.

Les demandes et commentaires B14–B17 sont intégrés dans `main` par #24 : création, lecture/recherche, édition/publication sous version et commentaires historisés. [Preuves de fusion et CI](../docs/quality/PR_READINESS_20261007.md). Les capsules et B2 restent proposés dans leurs PR séparées.

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

Lire [le plan de travail à trois](../docs/execution/BACKEND_A_TROIS.md) pour les fichiers réservés et dépendances. Les routes sont réparties dans `routes/api/identity.php`, `community.php` et `capsules-lab.php`, enregistrés une seule fois sous `/api/v1`. Les endpoints sont ajoutés au fil des lots de chaque domaine.

## Continuer le développement

Reprendre depuis [HANDOFF.md](../docs/execution/HANDOFF.md) et le suivi de son participant. Chaque développeur intègre `origin/main` en préservant ses travaux et respecte les prérequis de son lot. Contrôles disponibles : `composer lint`, `composer analyse`, `composer test` et `composer test:integration`, ce dernier uniquement sur une base locale/CI dédiée identifiée. S01/S02 restent partiels.

Le monolithe reste commun aux trois domaines. **BACKEND_GATE non reçu** : réception du backend P0 puis **GO_FRONTEND** humain précèdent toute application React. Le service B1 intégré reste partiel ; l'isolation du laboratoire et la démonstration B2 sont encore à compléter selon [la revue du 7 octobre](../docs/quality/MERGE_MADINA.md).
