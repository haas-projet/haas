# Scripts du dossier documentaire

Ces scripts vérifient/génèrent les documents. Ils ne testent pas l’application HAAS.

- `node scripts/validate-pack.mjs` : cohérence des identifiants, fichiers, sources et comptes.
- `node scripts/check-deployment-docs.mjs` : cohérence de la cible Systalink/Vercel.
- `node scripts/check-design-contrast.mjs` : calcul des 22 associations déclarées.
- `python scripts/build-documents.py` : HTML et PDF (dépendances : weasyprint, markdown-it-py, beautifulsoup4).
- `node scripts/build-presentation.cjs` : PowerPoint modifiable et notes (dépendance : pptxgenjs).

Installer ces outils de génération dans un environnement dédié, pas comme dépendances runtime de HAAS. La conversion du PowerPoint en PDF peut être faite dans PowerPoint ou LibreOffice. Aucune police n’est livrée ; les outils utilisent les polices présentes dans l’environnement. Après une régénération, contrôler visuellement les rendus et mettre à jour SHA256SUMS.

La validation documentaire inclut F18, les 122 lots, 90 AC, 23 UX et 10 skills. Les dépendances et la conservation des 106 anciens IDs sont vérifiées ; aucun test applicatif n’est exécuté par ces scripts.
