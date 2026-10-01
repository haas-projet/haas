# Modèles d’exploitation
Les fichiers de templates sont illustratifs : aucun ne configure un compte ou un serveur automatiquement. Ils restent hors backend/ et frontend/ jusqu’au lot autorisé. Remplacer les domaines d’exemple, injecter les secrets hors du dépôt et vérifier la configuration de la version réellement installée.

- backend.systalink.env.example : variables publiques et conventions à câbler dans Laravel.
- frontend.vercel.env.example : origine API publique, pas une clé.
- vercel.json.example : configuration Vite/SPA ; à placer dans frontend/vercel.json après GO_FRONTEND et vérifier les liens profonds.
- inventory.example.json : inconnues explicitement null, à compléter sans mot de passe.
- release-manifest.example.json : SHA et empreintes réels à produire lors d’une release.

Les scripts de provisionnement, services Unix, Nginx, PostgreSQL et CI restent à implémenter et tester dans les lots prévus. Rien dans ce dossier ne constitue une autorisation d’achat ou de déploiement. La documentation détaille les critères, sans livrer un faux script production universel.
