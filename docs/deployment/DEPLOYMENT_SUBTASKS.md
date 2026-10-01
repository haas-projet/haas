# Sous-lots de déploiement

Douze intentions rattachées aux 94 lots existants ; elles ne renumérotent pas le plan ni le suivi. Un lot et ses tests par commit, à subdiviser si nécessaire.

## DEP01 — Qualifier VPS et comptes

**Parent :** S01/S02. **Commit proposé :** `docs(deploy): qualifier Systalink Vercel et Ultimate`.

**À faire et vérifier :** Référence VPS, région, prix final, domaine contrôlé, plan Vercel et Qodana ; aucune valeur inventée.

**État initial du pack :** NON EXÉCUTÉ. Préserver les preuves réelles du dépôt.

## DEP02 — Authentification cross-origin

**Parent :** B07/B08. **Commit proposé :** `feat(auth): configurer Sanctum pour deux origines de confiance`.

**À faire et vérifier :** Cookies, CORS API/auth, prévols, redirections et tests de refus.

**État initial du pack :** NON EXÉCUTÉ. Préserver les preuves réelles du dépôt.

## DEP03 — Séparer le runner local

**Parent :** B36/B42. **Commit proposé :** `feat(lab): séparer le service local et ses permissions`.

**À faire et vérifier :** Identité/repertoires/SQL distincts ; aucun .env APP ; tests sous le vrai compte.

**État initial du pack :** NON EXÉCUTÉ. Préserver les preuves réelles du dépôt.

## DEP04 — Borner une exécution globale

**Parent :** B34/B37/BV206. **Commit proposé :** `fix(lab): sérialiser les runs et borner les ressources`.

**À faire et vérifier :** Claims concurrents, quota, mémoire/CPU/SQL et pannes documentés.

**État initial du pack :** NON EXÉCUTÉ. Préserver les preuves réelles du dépôt.

## DEP05 — Isoler B2

**Parent :** B38/F21. **Commit proposé :** `feat(demo): isoler B2 des cookies de la plateforme`.

**À faire et vérifier :** Hôtes hors parent HAAS, données fictives, aucune session de plateforme.

**État initial du pack :** NON EXÉCUTÉ. Préserver les preuves réelles du dépôt.

## DEP06 — Activer Ultimate

**Parent :** B43. **Commit proposé :** `ci(qodana): activer les analyses sur le projet autorisé`.

**À faire et vérifier :** Token réel en secret CI ; licence déclarée contrôlée ; rapports reliés au SHA.

**État initial du pack :** NON EXÉCUTÉ. Préserver les preuves réelles du dépôt.

## DEP07 — Préparer Vercel

**Parent :** F01/F05/F25. **Commit proposé :** `build(frontend): préparer Vite et les routes Vercel`.

**À faire et vérifier :** Origin API publique absolue, deep links, variables et tests navigateur.

**État initial du pack :** NON EXÉCUTÉ. Préserver les preuves réelles du dépôt.

## DEP08 — Tracer les artefacts

**Parent :** R01/R02. **Commit proposé :** `build(release): relier les artefacts au commit validé`.

**À faire et vérifier :** Manifeste API/frontend/runner/versions, empreintes et provenance.

**État initial du pack :** NON EXÉCUTÉ. Préserver les preuves réelles du dépôt.

## DEP09 — Publier dans le bon ordre

**Parent :** R03. **Commit proposé :** `ci(deploy): promouvoir API puis interface après validation`.

**À faire et vérifier :** Pas de main autopublié avant les tests ; accord humain et smoke tests.

**État initial du pack :** NON EXÉCUTÉ. Préserver les preuves réelles du dépôt.

## DEP10 — Sauvegarder et restaurer

**Parent :** R04. **Commit proposé :** `test(backup): vérifier la restauration hors production`.

**À faire et vérifier :** Sept générations chiffrées, clés hors VPS, test mesuré sans effacer prod.

**État initial du pack :** NON EXÉCUTÉ. Préserver les preuves réelles du dépôt.

## DEP11 — Vérifier le retour stable

**Parent :** R04. **Commit proposé :** `test(release): contrôler un retour compatible API et frontend`.

**À faire et vérifier :** Pas de migration inverse destructive ; workers et manifestes cohérents.

**État initial du pack :** NON EXÉCUTÉ. Préserver les preuves réelles du dépôt.

## DEP12 — Réception du déploiement

**Parent :** R06. **Commit proposé :** `docs(gate): consigner la réception Systalink Vercel`.

**À faire et vérifier :** Les 14 DEP-AC ont des preuves ; limites restantes explicites et GO_PRODUCTION humain.

**État initial du pack :** NON EXÉCUTÉ. Préserver les preuves réelles du dépôt.
