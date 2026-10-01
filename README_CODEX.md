# Démarrer ou reprendre avec Codex

1. Lire AGENTS.md, le début du maître, ADR-006 et COUPS_DE_MAIN.md. Le mail source reste inchangé.
2. Inspecter git status, branche, versions, code et tests existants ; préserver instructions et modifications non committées. Ne pas remplacer un dépôt par le pack.
3. Fusionner les 16 lots BH01–10/FH01–05/RH01 par ID ; conserver les 106 précédents et leurs preuves. tasks.json est la séquence active ; ne pas inventer les états du dépôt.
4. Backend d’abord : nouveaux schéma/consentement/API/transitions/rejeu/concurrence. BH10 avant audits et B44. Une ancienne réception ne couvre pas F18. Faire valider BACKEND_GATE et obtenir GO_FRONTEND.
5. Frontend après accord : UX21–23 et ajustements UX18–20, API réelle, états lisibles et consentements non précochés. Un frontend existant n’est pas détruit : corriger d’abord les contrats manquants.
6. Recette AC69–90, pilote RH01, cohérence des preuves puis livraison autorisée Systalink/Vercel.

Un lot = une intention et ses tests ; subdiviser les lots trop larges. Pas de git reset/clean/force, de push/merge/déploiement ou de fausse revue. Préserver le suivi ; laisser un bilan de session et le prochain lot réel. Les checks scripts/validate-pack.mjs valident le dossier, pas l’application.
