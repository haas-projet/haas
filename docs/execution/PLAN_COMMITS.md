# HAAS — Plan de développement

**122 lots : S2/B72/F40/R8.** Les 106 identifiants précédents sont conservés ; BH01–10/FH01–05/RH01 ajoutent16 intentions, pas des commits déjà réalisés. Statuts à rapprocher du dépôt réel.

### Phase S — Préparation

#### S01 — Inventaire et environnement

**Commit proposé :** `docs(setup): inventorier le dépôt et les contraintes`

**À réaliser.** Inspecter git status, branche, fichiers déjà présents et versions PHP/Composer/Node/npm/PostgreSQL. Lire les instructions existantes. Relever accès hébergement, courriel, Qodana et protections GitHub sans lire ou afficher de secrets. Créer VERSIONS.md et BLOCKERS.md avec valeurs réellement observées. Déploiement retenu : Relever la configuration d’un seul VPS Systalink, le domaine de confiance, le projet Vercel et son forfait. Ultimate est déclaré existant : vérifier le projet Qodana et le token sans l’afficher. Consigner panier/remise/taxes inconnus. Alignement mail : Lire le mail [M] et distinguer usages source/choix de conception ; relever état réel des projets/profils/question dans le dépôt. Coup de main : Lire ADR-006 et inspecter offres/consentements éventuels sans écraser le dépôt.

**Critères de sortie.** Aucune suppression ni réinitialisation ; les commandes indisponibles sont marquées NON_EXÉCUTÉ. Un dépôt existant n’est jamais rebaptisé pour simuler une création neuve. Déploiement retenu : Aucun compte ou achat présumé ; le budget distingue Systalink, Vercel et frais non chiffrés. Alignement mail : Aucune lecture inbox ni nouveau cahier technique de l’organisateur inventé.

**Traçabilité.** N05/N06 ; ARB01–ARB06 ; ADR-004 / DEP ; ADR-005 / [M]

#### S02 — Cadre d’exécution

**Commit proposé :** `docs(plan): fixer la séquence backend puis frontend`

**À réaliser.** Installer les consignes après comparaison avec les fichiers existants, conserver les deux sources du projet, adopter ADR-001 et créer le suivi. Définir branches courtes, propriétaire/relecteur de chaque lot et commandes de travail locales. Ne pas générer encore d’application React. Déploiement retenu : Appliquer ADR-004, qui remplace les deux VPS et la même origine. Répartir les douze sous-lots DEP sans renuméroter le suivi. Alignement mail : Adopter ADR-005 ; intégrer BC01–BC08 et FC01–FC04 sans effacer commits/statuts existants. Coup de main : Intégrer BH01–10, FH01–05 et RH01, sans renuméroter les identifiants existants ni convertir une ancienne revue en GO_FRONTEND.

**Critères de sortie.** Les trois personnes identifient la prochaine tâche, les preuves attendues et le point de passage BACKEND_GATE. Les inconnues juridiques, délais et budget restent explicites. Déploiement retenu : Ordre backend-first inchangé ; revue et autorisations humaines conservées. Alignement mail : 122 lots proposés ; charge réestimée et périmètre communautaire reçu.

**Traçabilité.** F01–F12 ; N05 ; ADR-004 / DEP ; ADR-005 / [M]

### Phase B — Backend complet

#### B01 — Squelette Laravel

**Commit proposé :** `chore(backend): initialiser Laravel et PostgreSQL`

**À réaliser.** Initialiser Laravel dans backend en préservant AGENTS.md ; configurer PostgreSQL, .env.example, identifiants UUID, date UTC et health minimal. Choisir les versions exactes compatibles. Aucune clé ni mot de passe réel dans les fichiers suivis.

**Critères de sortie.** Installation reproductible sur base dédiée vierge ; application démarre ; route health répond sans détail sensible ; composer.lock présent. Pas de changement silencieux de stack.

**Traçabilité.** N04/N05

#### B02 — Qualité PHP locale

**Commit proposé :** `chore(quality): configurer les contrôles PHP locaux`

**À réaliser.** Configurer Pint, PHPStan/Larastan compatibles et PHPUnit ; scripts Composer lint, analyse, test et test:integration. Créer un premier test d’architecture et interdire les dépendances HTTP dans Data/Services. Vérifier les licences des outils.

**Critères de sortie.** Les scripts annoncés existent et réussissent sur le squelette. Une violation témoin est détectée puis retirée. Aucun ignore général d’erreurs.

**Traçabilité.** N05/N06

#### B03 — CI backend initiale

**Commit proposé :** `ci(backend): exécuter les tests sur PostgreSQL`

**À réaliser.** Créer backend-ci avec service PostgreSQL isolé, installation verrouillée, formatage, analyse et tests. Actions épinglées sur références vérifiées, permissions minimales, aucun secret de production.

**Critères de sortie.** Exécution locale documentée ; CI distante constatée seulement si accessible. Un workflow non exécuté ne devient pas vert. Les contrôles requis ne restent pas indéfiniment pending à cause d’un filtre de chemins.

**Traçabilité.** AC28 ; N05

#### B04 — Contrat HTTP et erreurs

**Commit proposé :** `feat(api): normaliser les erreurs et la corrélation`

**À réaliser.** Configurer /api/v1, ApiExceptionRenderer, request_id, pagination et limites. Normaliser validation, auth, interdiction, conflit, CSRF, quotas et pannes ; préserver Retry-After. Commencer OpenAPI avec les schémas communs.

**Critères de sortie.** Tests 401/403/404/409/419/422/429/500/503 ; aucune trace SQL ni chemin interne en réponse. Une route API inconnue n’est pas du HTML de SPA.

**Traçabilité.** AC03/AC05 ; N01

#### B05 — Identité et référentiels

**Commit proposé :** `feat(identity): définir comptes rôles et états`

**À réaliser.** Créer User/Profile/Technology et enums rôle/état. Courriel privé normalisé unique, handle unique, statut actif/suspendu, email_verified_at et is_demo. La correspondance email_verified_at avec le champ conceptuel verified_at du cahier est documentée.

**Critères de sortie.** Contraintes et casts testés sur PostgreSQL ; routes d’inscription ne peuvent recevoir rôle/admin, author_id ou email_verified_at. Technologies évolutives en table, pas enum.

**Traçabilité.** AC04 ; F01/F11

#### B06 — Inscription

**Commit proposé :** `feat(auth): inscrire un membre sans élévation de droits`

**À réaliser.** FormRequest et service d’inscription, acceptation versionnée des conditions, pseudonyme 3–30 caractères, mot de passe 12–128 caractères. Utiliser le hasher Laravel adapté à la longueur Unicode supportée ; ne jamais tronquer silencieusement un mot de passe.

**Critères de sortie.** Compte membre créé, courriel non exposé ; doublon et champs protégés refusés. Mot de passe long vérifié réellement et jamais journalisé.

**Traçabilité.** AC01/AC04

#### B07 — Sessions et CSRF

**Commit proposé :** `feat(auth): sécuriser les sessions Sanctum`

**À réaliser.** Configurer le mode SPA : csrf-cookie, login, logout, session régénérée/invalidation. Limiter les tentatives de connexion. Tester les deux origines de confiance et la configuration locale cohérente. Pas de JWT ni token navigateur persistant. Déploiement retenu : Configurer Sanctum pour SPA Vercel et API Systalink sur sous-domaines de confiance. CORS exact, paths auth et API, XSRF, credentials, redirections et erreurs avec CORS.

**Critères de sortie.** Tests cookies/CSRF avec le middleware effectivement actif, pas seulement des tests Laravel qui le désactivent par défaut. Login valide/invalide, logout et tentative forgée vérifiés. Déploiement retenu : Tester origines autorisées/interdites, prévols, CSRF actif et absence de token localStorage ; garder la recette sur domaines réels pour DEP-AC02.

**Traçabilité.** AC01/AC02 ; N01 ; ADR-004 / DEP

#### B08 — Courriels de compte

**Commit proposé :** `feat(auth): vérifier le courriel et réinitialiser le mot de passe`

**À réaliser.** Configurer liens signés expirants, renouvellement limité, mot de passe oublié et réinitialisation à usage unique via mécanismes Laravel. Réponse de demande de reset indépendante de l’existence du compte. Transport local de test séparé du service réel. Déploiement retenu : Construire les liens depuis APP_URL API et FRONTEND_URL autorisée. Ne pas altérer l’hôte d’une URL signée pour l’envoyer au frontend.

**Critères de sortie.** Expiration, signature altérée, lien déjà consommé et throttling vérifiés ; compte non vérifié ne publie pas. Délivrabilité réelle est un contrôle d’environnement, pas une assertion tirée d’un mail fake. Déploiement retenu : Aucune redirection ouverte, lien valide vers le bon environnement ; aucune donnée de compte révélée.

**Traçabilité.** AC01/AC02 ; ADR-004 / DEP

#### B09 — Autorisations et compte courant

**Commit proposé :** `feat(identity): exposer la session et contrôler les capacités`

**À réaliser.** Créer /me, MeResource, EnsureAccountIsActive et Policies de départ. Séparer lecture publique et actions vérifiées. Définir capacités par ressource sans Gate::before universel. Prévoir le canal d’information pour compte suspendu.

**Critères de sortie.** Visiteur, non vérifié, membre, modérateur et admin ont des tests négatifs. Un admin ne devient pas auteur d’une demande par son rôle.

**Traçabilité.** AC02/AC03/AC04/AC09

#### B10 — Profils et technologies

**Commit proposé :** `feat(profiles): publier des profils sans données privées`

**À réaliser.** API de profil public et modification de son propre profil : bio 500 caractères, 8 technologies maximum, langue, pays facultatif, lien GitHub HTTPS. Avatars initiales ; compteurs issus des contributions réelles. Alignement mail : Garder des profils réutilisables par l’annuaire volontaire ; préférences détaillées dans BC06.

**Critères de sortie.** Adresse de courriel, IP, hash, tokens et état privé exclus du profil public. Nombre de contributions non contrôlable depuis le client ; limites testées. Alignement mail : Compte courant et Resource publique séparés.

**Traçabilité.** F11 ; AC27 ; ADR-005 / [M]

#### B11 — Schéma de collaboration

**Commit proposé :** `feat(requests): créer les tables de demandes et résolutions`

**À réaliser.** Migrations help_requests, technologies associées, comments, proposals et resolutions. UUID, foreign keys, lock_version, états et index utiles. Ajouter l’unicité partielle d’une résolution active et documenter la contrainte d’appartenance proposition/demande.

**Critères de sortie.** Migration sur base neuve ; index partiel testé ; référence croisée interdite par les contrôles/contraintes retenus. Rollback de test uniquement sur base dédiée.

**Traçabilité.** AC10 ; F02/F03/F04

#### B12 — Audit et révisions

**Commit proposé :** `feat(audit): tracer les modifications métier autorisées`

**À réaliser.** Créer AuditWriter et content_revisions avec liste blanche de métadonnées. Les services écrivent l’événement métier dans leur transaction. Prévoir le retrait de données secrètes sans les recopier dans l’historique.

**Critères de sortie.** Rollback annule données et audit ; aucun mot de passe, token, cookie ni corps complet de requête journalisé. Acteur et date proviennent du serveur.

**Traçabilité.** N07 ; AC25

#### B13 — Idempotence des commandes

**Commit proposé :** `feat(api): dédupliquer les commandes par intention`

**À réaliser.** IdempotencyService et stockage utilisateur/route-cible/clé/empreinte/réponse. Contrôle concurrent par unicité et transaction, expiration 24 h proposée. Autorisation courante recontrôlée avant le rejeu. Ne pas conserver de réponse privée au-delà du besoin.

**Critères de sortie.** Même clé et même payload : une écriture ; payload différent : 409 ; deux utilisateurs n’accèdent pas à la réponse de l’autre ; cas réellement concurrent testé.

**Traçabilité.** AC06/AC10 ; N01

#### B14 — Créer une demande

**Commit proposé :** `feat(requests): enregistrer un brouillon ou une demande`

**À réaliser.** StoreHelpRequestRequest, CreateHelpRequestData/Service et Resources. Appliquer les limites du cahier, code inerte et contrôle indicatif de secrets. Distinguer explicitement l’intention brouillon/publication dans OpenAPI ; aucun state libre en PATCH. Atelier : intégrer help_intent sans multiplier les workflows ; accueillir une piste déjà produite avec ou sans IA.

**Critères de sortie.** AC05/AC06 : validations et double envoi ; brouillon privé et publication visible selon Policy. Champs protégés rejetés. Les règles allégées du brouillon sont écrites dans le contrat, pas implicites.

**Traçabilité.** AC03–AC07 ; F02

#### B15 — Lire et rechercher les demandes

**Commit proposé :** `feat(requests): filtrer les lectures selon la visibilité`

**À réaliser.** ListHelpRequestsQuery et FindVisibleHelpRequestQuery, recherche titre/contenu autorisé/technologies, filtre état, tri autorisé et pagination 20 max 50. Eager loading des relations nécessaires.

**Critères de sortie.** Brouillons d’autrui et contenus masqués absents du détail, listes, résultats et compteurs. Requête avec tri arbitraire ou page_size excessif contrôlée ; pas de N+1 sur le jeu de référence.

**Traçabilité.** AC03/AC15/AC25 ; F12

#### B16 — Modifier une demande

**Commit proposé :** `feat(requests): protéger l’édition par version`

**À réaliser.** UpdateHelpRequestRequest/Data/Service. Comparaison lock_version, version incrémentée et révision. Clarifications après contributions avec note de modification. La publication d’un brouillon a une commande documentée distincte si nécessaire. Atelier : intégrer help_intent sans multiplier les workflows ; accueillir une piste déjà produite avec ou sans IA.

**Critères de sortie.** Édition par non auteur refusée ; conflit 409 ne change rien ; les champs d’état/résolution/auteur ne sont pas modifiables. Historique cohérent.

**Traçabilité.** AC03/AC05 ; F02

#### B17 — Commentaires

**Commit proposé :** `feat(collaboration): enregistrer des commentaires historisés`

**À réaliser.** API création et édition de ses commentaires ; Markdown restreint 1–4 000 caractères, code traité comme texte, idempotence et version d’édition. Notifications préparées par événement. Un commentaire seul ne change pas l’état de la demande.

**Critères de sortie.** Double requête ne duplique pas ; auteur tiers refusé ; HTML reste inerte ; commentaire sur contenu non accessible interdit.

**Traçabilité.** AC06/AC07 ; F03

#### B18 — Propositions de solution

**Commit proposé :** `feat(collaboration): structurer les propositions de résolution`

**À réaliser.** Proposals : diagnostic/correctif/vérification/limites chacun 20–4 000 caractères, code facultatif 12 000. Première proposition d’une demande ouverte la fait passer en cours. Interdire l’édition silencieuse d’une proposition acceptée.

**Critères de sortie.** Une simple URL ne remplace pas les champs ; une proposition valide modifie l’état une seule fois ; tests de propriété et d’idempotence.

**Traçabilité.** AC08 ; F03/F04

#### B19 — Accepter une proposition

**Commit proposé :** `feat(resolutions): accepter une solution sous verrou`

**À réaliser.** ResolveHelpRequestService transactionnel : relecture verrouillée, Policy, lock_version, appartenance proposition, une résolution active, attribution/audit. Self-resolution admise et étiquetée ; notifications après commit.

**Critères de sortie.** Auteur seulement y compris face à admin ; proposition étrangère rejetée ; deux acceptations simultanées ne créent pas deux résolutions ; rollback complet.

**Traçabilité.** AC09/AC10 ; RM01/RM02

#### B20 — Rouvrir une demande

**Commit proposé :** `feat(resolutions): conserver l’historique des réouvertures`

**À réaliser.** Commande reopen avec motif et version. Révoquer la résolution active, remettre l’état selon contrat, conserver les preuves datées et marquer les capsules liées à revoir. Partager l’ordre de verrous avec résolution/édition.

**Critères de sortie.** Ancienne résolution conservée ; capsule signalée sans faux retrait automatique ; course reopen/resolve testée ; non auteur refusé.

**Traçabilité.** AC11 ; F04

#### B21 — Archiver et non-retenir

**Commit proposé :** `feat(collaboration): motiver l’archivage et le rejet`

**À réaliser.** Commandes explicites d’archivage et de proposition non retenue avec motif. Distinguer clôture administrative et résolution par l’auteur. Autorisations propres à chaque action.

**Critères de sortie.** Archivage ne crée ni résolution ni preuve. Décision conservée avec acteur/motif ; refus d’un tiers ; états incompatibles donnent conflit.

**Traçabilité.** F03/F04/F09 ; N07

#### B22 — Schéma des capsules

**Commit proposé :** `feat(capsules): versionner les capsules et contributions`

**À réaliser.** Créer capsules, capsule_versions, capsule_contributors et artefacts. Source demande résolue ou origine éditoriale explicite, slug, version unique, provenance, limites et technologies. Enums distincts de ceux des demandes.

**Critères de sortie.** Clés/contraintes de version et contributeurs testées. Une capsule, sa brique facultative et son laboratoire facultatif restent trois objets distincts.

**Traçabilité.** F05/F06 ; AC13

#### B23 — Brouillons de capsule

**Commit proposé :** `feat(capsules): créer des versions documentées`

**À réaliser.** Création/édition d’un brouillon par auteur ou contributeur habilité selon contrat ; diagnostic, procédure, versions compatibles, limites et provenance. Ressources distinctes publiques/édition.

**Critères de sortie.** Brouillon absent du catalogue public ; source étrangère non autorisée refusée ; données requises et version testées. Aucun kit upload utilisateur ajouté.

**Traçabilité.** F05 ; AC03/AC12

#### B24 — Soumettre à la revue

**Commit proposé :** `feat(capsules): organiser une revue indépendante`

**À réaliser.** Transitions draft → in_review → changes_requested → in_review, avec note du réviseur habilité. Aucun auteur ne valide seul sa revue. Journal des changements et contrôle de diffusion.

**Critères de sortie.** Transitions illégales et auto-revue refusées même pour un admin. Revue ne signifie pas publication ni certification de sécurité.

**Traçabilité.** AC12 ; F06

#### B25 — Publier une version immuable

**Commit proposé :** `feat(capsules): figer les versions publiées`

**À réaliser.** PublishCapsuleVersionService vérifie revue indépendante, documentation, provenance et autorisation du kit. Publier atomiquement ; correction = nouvelle version ; ne jamais hériter automatiquement des tests.

**Critères de sortie.** AC12/AC13 : même admin auteur ne contourne pas ; impossible d’écraser le body publié ; anciennes preuves toujours rattachées à l’ancien digest.

**Traçabilité.** AC12/AC13 ; RM03

#### B26 — Catalogue de capsules

**Commit proposé :** `feat(capsules): rechercher les versions visibles`

**À réaliser.** Queries liste/détail avec slug et version choisie ; filtres technologie et présence de laboratoire pour cette version ; historique de versions autorisées ; absence de cache partagé contenant des permissions personnelles.

**Critères de sortie.** Version retirée introuvable dans recherche normale ; badge de laboratoire ne fuit pas sur une autre version ; aucune capsule inventée sur résultat vide.

**Traçabilité.** AC15/AC25 ; F12

#### B27 — Téléchargement contrôlé

**Commit proposé :** `feat(artifacts): contrôler l’accès aux kits versionnés`

**À réaliser.** Stockage privé et téléchargement via contrôle serveur courant, digest et notices. Aucun ZIP soumis par utilisateur. distribution_status inactif tant que les conditions d’évaluation ne sont pas approuvées ; message explicite plutôt que faux succès.

**Critères de sortie.** Retrait interdit immédiatement nouvel accès par ancienne URL de l’API ; un lien signé seul ne contourne pas le retrait. Aucun chemin disque divulgué ; fichiers déjà téléchargés non prétendument révocables.

**Traçabilité.** AC15/AC25 ; ARB05

#### B28 — Favoris et réutilisation

**Commit proposé :** `feat(capsules): rattacher favoris et retours aux versions`

**À réaliser.** PUT/DELETE favori idempotent et privé ; un retour actif par utilisateur/version, environnement/date/résultat/commentaire et historique. Contributions internes étiquetées ; pas de points pour auto-validation.

**Critères de sortie.** Autrui ne lit pas les favoris ; doublons contrôlés ; retour humain distinct d’un rapport machine ; version obligatoire et autorisée.

**Traçabilité.** AC14/AC27 ; F07/F11

#### B29 — Notifications internes

**Commit proposé :** `feat(notifications): livrer les événements sans doublons`

**À réaliser.** Liste paginée, compteur et marquage lu/non lu. Abonner les événements proposition/acceptation/réouverture/revue/laboratoire. Dédupliquer par destinataire/événement et documenter la reprise après transaction.

**Critères de sortie.** Échec d’envoi ne perd pas la résolution ; aucun code/secret dans notification ; impossible de lire ou marquer celle d’un autre membre. Retry ne duplique pas.

**Traçabilité.** AC08 ; F10

#### B30 — Signalements

**Commit proposé :** `feat(moderation): recueillir des signalements ciblés`

**À réaliser.** Créer reports avec ressource, catégorie et texte 20–1 000 ; un actif par membre/ressource ; liste admin avec filtres état/date/catégorie. Pas d’exposition publique du signalement privé.

**Critères de sortie.** Ressource autorisée requise ; doublon et abus bornés ; membre normal n’accède pas à la file de modération.

**Traçabilité.** AC25 ; F09

#### B31 — Retrait de contenus

**Commit proposé :** `feat(moderation): retirer les contenus avec motif`

**À réaliser.** Actions motivées : sans suite, correction, masquer, retirer version. Invalider les caches et accès aux kits ; expurger secrets de l’historique exposé selon procédure. Préserver l’audit nécessaire sans recopier le secret.

**Critères de sortie.** URL directe, recherche, notification liée et ancien lien de kit vérifiés après retrait. Pas de simple masquage React ; révocation du secret à la source expliquée.

**Traçabilité.** AC15/AC25 ; N01

#### B32 — Suspensions et rôles

**Commit proposé :** `feat(identity): révoquer les sessions des comptes suspendus`

**À réaliser.** Commandes admin de suspension/changement sensible de rôle avec motif/audit et révocation des sessions. Prévenir un admin de se verrouiller hors du seul accès d’exploitation selon règle documentée.

**Critères de sortie.** Sessions précédentes invalidées ; aucune écriture/lab possible ; message d’information accessible sans exposer les données privées. Modérateur ne suspend pas.

**Traçabilité.** AC26 ; F09

#### B33 — Registre du laboratoire

**Commit proposé :** `feat(lab): définir les exécutions et scénarios approuvés`

**À réaliser.** LabDefinition/Run/Result, enums, runner registry fixe, digests code/suite/version, configuration kill switch. Connexions de contrôle et fixtures restreintes. Pas de classe de runner déterminée par l’utilisateur.

**Critères de sortie.** Scénario inconnu, version retirée ou disabled refusés ; aucune API publique d’écriture des résultats. Schéma de rapport documenté.

**Traçabilité.** AC16/AC18 ; F08

#### B34 — Lancement et quotas

**Commit proposé :** `feat(lab): borner les lancements et la concurrence`

**À réaliser.** StartLabRunService, clé idempotence, réponse 202 et suivi. Une exécution active/membre, deux globales, cinq lancements/heure/membre comme limites proposées. Contrôle atomique des places actives et queue bornée. Déploiement retenu : Appliquer LAB_MAX_RUNNING=1 pour les runs simples et le comparateur ; cinq unités/h/membre, une ou deux unités selon opération.

**Critères de sortie.** Deux appels simultanés ne dépassent pas les quotas ; doublon n’occupe pas deux places ; 429 conserve Retry-After ; code/URL/command transmis = 422. Déploiement retenu : Deux lancements concurrents ne dépassent pas un run actif global ; réserver sans double comptage au rejeu.

**Traçabilité.** AC18/AC19 ; F08 ; ADR-004 / DEP

#### B35 — Brique B1

**Commit proposé :** `feat(b1): traiter les événements fictifs sans doublon`

**À réaliser.** Créer le module Laravel approuvé : event_id/order_ref/amount_minor/currency, transaction et unicité (run_id,event_id). Fixture entièrement fictive. B1-01 nominal, 02 doublon, 03 distincts, 04 invalide, 05 concurrence.

**Critères de sortie.** B1-01 à 04 passent sur PostgreSQL ; aucun paiement réel. Traitements distincts donnent deux commandes ; invalidation aucune écriture partielle. Tests du défaut pédagogique séparés.

**Traçabilité.** AC16/AC21 ; B1-01–B1-05

#### B36 — Worker et rapports réels

**Commit proposé :** `feat(lab): exécuter les scénarios sur un worker restreint`

**À réaliser.** ExecuteLabRunJob/Service via registry connu. Runtime du worker séparé sans .env applicatif lisible ni identifiants d’administration ; fixtures séparées. Rapport attendu/observé/digests/date/durée ≤64 Ko. Déploiement retenu : Implémenter le runner comme service local séparé sous haas-lab : paquet approuvé distinct, canal Unix borné, rôle SQL haas_lab, aucun bootstrap Laravel principal ni accès aux secrets APP.

**Critères de sortie.** Runs séparés ; résultat recalculé ; service exécuteur incapable de lire users/contenus privés (l’orchestrateur Laravel reste côté APP). Tester passed ET failed ; jamais une constante passée pour simuler le calcul. Déploiement retenu : Exécuter les tests négatifs d’accès aux fichiers et haas_app sous l’identité runner. Une base distincte seule n’est pas une isolation.

**Traçabilité.** AC16/AC17/AC21 ; N01 ; ADR-004 / DEP

#### B37 — Pannes du laboratoire

**Commit proposé :** `fix(lab): réconcilier les tâches interrompues`

**À réaliser.** Temps cible 15 s et abandon technique >20 s ; retry_after cohérent, tentatives bornées, claim atomique et finalisation unique. Réconciliation queued/running orphelins ; purge fixtures au plus tard 24 h. Déploiement retenu : Vérifier la perte du service local, le timeout, le verrou global et la continuité de l’API pendant un test.

**Critères de sortie.** Arrêt brutal, notification perdue et tentative rejouée n’engendrent ni faux succès ni double résultat terminal. Échec fonctionnel distinct de panne technique/délai. Déploiement retenu : Jamais un faux résultat positif ; file bornée et reprise observable ; mesurer la charge avant ouverture.

**Traçabilité.** AC19/AC20 ; N08 ; ADR-004 / DEP

#### B38 — API de démonstration B2

**Commit proposé :** `feat(b2): confirmer les reprises de commande fictive`

**À réaliser.** Construire uniquement l’API de démonstration, contrat JSON et idempotence par clé stable ; origine/runtime/configuration distincts de la session HAAS. Pas de composant React avant BACKEND_GATE. Déploiement retenu : API B2 cookie-free sur hôte hors .haas.example.com, sous un compte/pool et une base fictive distincts, même VPS.

**Critères de sortie.** Deux envois avec même clé donnent une seule commande fictive. Payload différent rejeté. Aucune session HAAS utilisée et données bornées/purgées. Déploiement retenu : Aucun accès aux sessions/comptes HAAS ; uniquement clés stables et entrées bornées de démonstration.

**Traçabilité.** AC23 ; B2 backend ; ADR-004 / DEP

#### BC01 — Schéma des projets et découverte volontaire

**Commit proposé :** `feat(community): ajouter les données des projets et de découverte`

**À réaliser.** Créer projects/project_technologies, états séparés, préférences de profiles et FK facultative help_requests.project_id. Étendre les enums et contraintes selon COMMUNAUTE_ET_PROJETS. Migration non destructive et factories.

**Critères de sortie.** Migrations montée/base neuve et données antérieures testées sur PostgreSQL ; uniques/FK valides ; opt-in false par défaut ; pas de données privées seedées.

**Traçabilité.** F16/F17 ; AC53/AC60

#### BC02 — Brouillons et édition de projet

**Commit proposé :** `feat(projects): créer et modifier ses fiches projet`

**À réaliser.** Requests/DTO/Policy/Services/Resources pour créer un brouillon et éditer ses champs. Réutiliser IdempotencyService, audit, lock_version et référentiel technologies. Liens externes inertes.

**Critères de sortie.** Tests ownership, mass assignment, validation, double création, conflit 409 et absence de fetch/clonage serveur. Aucune identité client acceptée.

**Traçabilité.** F16 ; AC53/AC54/AC56/AC57

#### BC03 — Publication et catalogue des projets

**Commit proposé :** `feat(projects): publier et rechercher les projets`

**À réaliser.** Commandes de publication/archivage ; Queries list/detail avec visibilité, filtres whitelist, pagination bornée et ordre stable ; Resources publiques/capacités.

**Critères de sortie.** Brouillons/masqués absents, publication conditionnelle, état de phase déclaratif, archivage non destructif et aucun compteur privé.

**Traçabilité.** F16 ; AC53/AC55/AC56

#### BC04 — Demandes liées à un projet

**Commit proposé :** `feat(collaboration): relier une demande à son projet`

**À réaliser.** Étendre création/édition de demande et GET projets/{id}/requests ; seul propriétaire relie sa demande à son projet publié. Lien stable après publication. Réutiliser discussion existante, sans nouveau fil projet.

**Critères de sortie.** Tiers/projet masqué/archivé privés refusés ; lien facultatif accepté ; modifications concurrentes et compteurs sous visibilité ; demandes autonomes inchangées.

**Traçabilité.** F16/F02 ; AC58/AC67

#### BC05 — Modération des projets et visibilité liée

**Commit proposé :** `feat(moderation): protéger la visibilité des projets et échanges`

**À réaliser.** Ajouter project aux types de signalement autorisés ; masquer/restaurer avec audit et motif. Appliquer visibilité parent aux demandes/cas/comments/proposals, caches/notifications ; expurger sources de capsules et traiter secrets selon règle existante.

**Critères de sortie.** Tester accès URL, listes, caches et demandes liées après masquage/suspension ; restauration ne republie pas un brouillon. Modérateur ne réécrit pas propriétaire ni solution.

**Traçabilité.** F16/F09 ; AC54/AC59/AC62

#### BC06 — API de découverte des développeurs

**Commit proposé :** `feat(developers): exposer un annuaire volontaire`

**À réaliser.** Étendre UpdateProfileRequest/Data/Service pour préférences whitelist ; GET /developers et DeveloperSummaryResource avec actif+vérifié+opt-in, filtres technologie/disponibilité et pagination.

**Critères de sortie.** AC60–63 passent : pas d’email/last_login/IP, retrait cache, profil tiers non modifiable, aucune promesse online ni classement ; types OpenAPI mis à jour.

**Traçabilité.** F17 ; AC60/AC61/AC62/AC63

#### BV201 — Schéma des cas et intentions

**Commit proposé :** `feat(cases): définir les cas versionnés et leurs parents`

**À réaliser.** Ajouter help_intent aux demandes, VerificationCase/Revision, enums, DTO, migrations et contraintes parent XOR, révisions uniques, auteur serveur. Conserver les anciennes données et états.

**Critères de sortie.** Migrations neuves et incrémentales passent ; parents invalides interdits ; aucun champ protected assignable.

**Traçabilité.** F13 ; AC33–AC35

#### BV202 — Créer et soumettre un cas

**Commit proposé :** `feat(cases): publier des cas de vérification documentaires`

**À réaliser.** FormRequests, Policies, Create/Revise/SubmitVerificationCaseService, Resources et lectures filtrées. Rejeter exécution dynamique et maintenir les révisions figées.

**Critères de sortie.** Auteur seul édite ses brouillons ; la modification invalide la revue ; masquage direct et listes testés.

**Traçabilité.** F13 ; AC34–AC36/AC51

#### BV203 — Revue et intégration des cas

**Commit proposé :** `feat(cases): relier une révision à un scénario approuvé`

**À réaliser.** Revue distincte/motif ; case_scenario_links alimenté via manifeste CI/release validé. Pas de code ni de chemin exécutable saisi depuis admin ; audit et notification sans doublon.

**Critères de sortie.** Reviewed distinct d’integrated ; pas d’auto-revue ; ancien lien reste attaché à sa révision.

**Traçabilité.** F13/F15 ; AC35–AC37/AC46

#### BV204 — Profils de comparaison B1

**Commit proposé :** `feat(comparisons): enregistrer les profils de comparaison approuvés`

**À réaliser.** Déclarer dans le registre baseline pédagogique/candidate liés à versions/digests/suite. Baseline non recommandée et non distribuée ; profils non administrables en code via HTTP.

**Critères de sortie.** Paire arbitraire/incompatible/retirée refusée ; intégrité manifeste testée ; scénarios fixes.

**Traçabilité.** F14 ; AC38/AC51

#### BV205 — Données et contrat des comparaisons

**Commit proposé :** `feat(comparisons): figer les entrées et les deux exécutions`

**À réaliser.** ComparisonRun/State/Outcome, migrations liens enfants et unique(comparison_id,side), snapshot d’entrée/seed/oracle/config. Requêtes et Resource sans fuite.

**Critères de sortie.** Même empreinte canonique pour les deux côtés, namespaces distincts, IDs/digests figés, exemple OpenAPI validé.

**Traçabilité.** F14 ; AC39

#### BV206 — Lancement idempotent et quotas partagés

**Commit proposé :** `feat(comparisons): réserver atomiquement deux unités de test`

**À réaliser.** StartComparisonService avec Policies, Idempotency-Key, réservation deux crédits, une opération/membre et mêmes limites globales que lab simple. Réponse 202 et suivi. Déploiement retenu : Réserver atomiquement deux unités de comparaison ; un seul run actif global partagé avec le lab simple.

**Critères de sortie.** Deux créations concurrentes testées sur PostgreSQL ; aucune double consommation au rejeu ; 409/429 corrects. Déploiement retenu : Comparer et lancer un run en concurrence ne contourne ni quota ni verrou.

**Traçabilité.** F14 ; AC41/AC42/AC52 ; ADR-004 / DEP

#### BV207 — Exécuter les deux côtés réels

**Commit proposé :** `feat(comparisons): exécuter la paire sur le worker restreint`

**À réaliser.** ExecuteComparisonJob/Service : enfants séquentiels, fixtures neuves, profils figés, claim/lease, finalisation unique et limite totale proposée 50 s. Pas de code entrant. Déploiement retenu : Appeler le même runner local restreint ; enfants successifs. Garder empreintes et fixtures séparées sans réexécuter arbitrairement une autre release.

**Critères de sortie.** Le doublon naïf produit réellement le défaut ; candidat conforme ; tests négatifs de permissions et isolation. Déploiement retenu : Deux observations nouvelles, manifestes vérifiés, données attendues distinctes des données observées.

**Traçabilité.** F14 ; AC39/AC40/AC43 ; ADR-004 / DEP

#### BV208 — Conclusions et pannes de comparaison

**Commit proposé :** `feat(comparisons): distinguer amélioration régression et panne`

**À réaliser.** ComparisonOutcomeCalculator pur, compatibilité assertions, cinq conclusions, ReconcileComparisonsService et retrait sécurisé des rapports.

**Critères de sortie.** Tester mixed, unchanged avec deux échecs, panne enfant, retry et données sensibles ; jamais de victoire automatique du candidat.

**Traçabilité.** F14 ; AC43–AC45

#### BV209 — Fiche de vérification et attributions

**Commit proposé :** `feat(evidence): relier les cas contributions et preuves versionnées`

**À réaliser.** VerificationSummaryQuery/Resource rassemble types de preuve distincts, liens de contributeurs, limite et états absents. Visibilité cohérente avec les sources ; pas de score global.

**Critères de sortie.** Nouvelle version sans badge hérité ; aucune auto-validation indépendante ; mêmes restrictions aux caches et URL.

**Traçabilité.** F15 ; AC45/AC46

#### BC07 — Questions sans formulaire de panne

**Commit proposé :** `feat(help): permettre les questions de connaissance sans code`

**À réaliser.** Après l’enum HelpIntent existant, ajouter ask_question. Validation conditionnelle goal/observed, expected/attempts nullables uniquement pour ce mode ; conserver règles des autres modes et les fils existants.

**Critères de sortie.** Tests migration contenu antérieur, 422 ciblée, question sans code/projet, commentaires sans laboratoire et anciennes demandes unblock/review/reproduce sans régression.

**Traçabilité.** F02/F03 ; AC64/AC68

#### BC08 — Recette API de la communauté

**Commit proposé :** `test(community): vérifier projets annuaire et questions`

**À réaliser.** Exécuter tests intégration PostgreSQL/routes F16/F17, contrats OpenAPI et négatifs, pagination, liens et caches. Compléter dictionnaire données et inventaire endpoints ; seeds explicites de projets éditoriaux.

**Critères de sortie.** Tous cas serveur AC53–64 observés ; preuves au SHA, pas de mocks dans routes P0 ; tests B1/proposals initiaux toujours verts ; charge et dépendances documentées.

**Traçabilité.** F01–F18 ; AC53–64/AC67/AC68

#### BV210 — Recette transversale de l’atelier

**Commit proposé :** `test(workshop): couvrir le parcours et les courses concurrentes`

**À réaliser.** Ajouter contrat/API A-B-C cas/revue/manifeste/comparaison/fiche et tests concurrence partagés. Mettre à jour matrice, OpenAPI et menaces ; aucun frontend.

**Critères de sortie.** AC33–AC46/AC50–AC52 partie backend ont commandes réelles ; failures pannes reproductibles et démo honnête.

**Traçabilité.** F13–F15 ; AC33–AC52 backend

#### BH01 — Schéma et ouverture aux coups de main

**Commit proposé :** `feat(help-offers): définir les états et l’ouverture volontaire`

**À réaliser.** Créer migration additive help_offers, HelpOfferState, HelpContributionCategory ; help_open=false, help_categories et préférences facultatives. Contraintes pending unique et cohérence accepted/request. Préparer réglages propriétaire et horodatages de consentement.

**Critères de sortie.** Migration vierge/existante sans perte ; aucun projet ouvert automatiquement ; états invalides/FK/indexes testés sur PostgreSQL.

**Traçabilité.** AC69/AC70

**Dépendances explicites.** BC01, B13

#### BH02 — Occasions de contribuer et préférences explicables

**Commit proposé :** `feat(discovery): proposer des besoins publics filtrables`

**À réaliser.** Créer ListHelpOpportunitiesQuery et Resource ; distinguer demande ouverte et projet volontaire, préserver fil direct ; filtres bornés, raisons basées sur champs déclarés, ordre stable. Étendre profil sans modifier opt-in annuaire.

**Critères de sortie.** AC71/72 : données cachées absentes, pas de compteurs pending ou score expert ; pas de doublon de carte favorisé ; préférences retirable, API publique non personnalisée en cache.

**Traçabilité.** AC71/AC72/AC87

**Dépendances explicites.** BH01, BC06

#### BH03 — Proposer un apport avec consentement et limites

**Commit proposé :** `feat(help-offers): soumettre une offre consentie sans doublon`

**À réaliser.** FormRequest/DTO/Policy/CreateHelpOfferService ; résumé public futur, résultat limité, accord non précoché côté UI ; quotas atomiques 5/jour et 5 pending, 1 pending par projet ; idempotence commune.

**Critères de sortie.** Acteur non propriétaire actif/vérifié ; refus champs serveur et consentement absent ; aucune offre publique ; double requête et concurrence de quotas testées.

**Traçabilité.** AC73–AC76

**Dépendances explicites.** BH01, B13

#### BH04 — Consulter ses offres sans fuite

**Commit proposé :** `feat(help-offers): protéger les offres reçues et envoyées`

**À réaliser.** ListMyHelpOffersQuery, HelpOfferResource privée et lecture individuelle ; propriétaire/proposant ; filtre sent/received, pagination, expiration serveur. Modération via signalement autorisé.

**Critères de sortie.** Autre membre/admin non habilité ne lit pas ; pas email ou cache public ; seules données visibles renvoyées et catégories datées.

**Traçabilité.** AC77/AC83

**Dépendances explicites.** BH03

#### BH05 — Accepter et créer un seul échange public

**Commit proposé :** `feat(help-offers): ouvrir un échange après double consentement`

**À réaliser.** AcceptHelpOfferService destination=create ; Policy, verrous et lock_version ; relire opt-in/délai/acteurs ; validation complète et service de demande réutilisés, public consent propriétaire et proposant ; audit/projection unique dans la transaction.

**Critères de sortie.** Refus consentement manquant/texte invalide ; owner fixé ; rollback ne laisse ni demande ni offre accepted ; résumé exact et acteur système distinct.

**Traçabilité.** AC78/AC80

**Dépendances explicites.** BH03, B14, BC07, B29

#### BH06 — Rattacher une offre à un échange choisi

**Commit proposé :** `feat(help-offers): rattacher une aide au bon échange`

**À réaliser.** Accepter mode attach explicitement ; même projet et propriétaire, public ouvert/en cours ; projection consentie sans altération du fil ; réponse de rejeu commune, ordre de verrous identique aux autres commandes.

**Critères de sortie.** Cas fil autre projet/auteur/masqué/résolu refusés ; acceptations concurrentes même offre ne dupliquent pas le lien ; aucune adhésion équipe.

**Traçabilité.** AC79/AC80

**Dépendances explicites.** BH05, BC04

#### BH07 — Refuser retirer expirer et fermer proprement

**Commit proposé :** `feat(help-offers): gérer le retrait et l’expiration des offres`

**À réaliser.** Services decline/withdraw/expire ; TTL7jours, expiration lors commandes et cron ; fermeture projet, archivage, catégorie retirée et suspensions invalident pending sans faux refus ; accepted reste historique.

**Critères de sortie.** Course accept/withdraw/close/suspend aboutit à un seul état valide ; aucun fil créé après fermeture ; motifs privés ; pas de malus ; pending périmé libère la contrainte.

**Traçabilité.** AC81/AC82/AC83/AC85

**Dépendances explicites.** BH06, B32

#### BH08 — Projections publiques progrès et notifications

**Commit proposé :** `feat(collaboration): relier les coups de main aux progrès observés`

**À réaliser.** Événement help_offer_accepted explicitement attribué, non commentaire usurpé ; ProjectProgressQuery depuis fils/résolutions/capsules ; notification after-commit unique avec rattrapage ; reports type help_offer et masquage.

**Critères de sortie.** Offre accepted affiche collaboration commencée jamais travail terminé ; aucun refus/pending/texte privé public ; caches et liens retirés ; notif sans corps sensible et unique au rejeu.

**Traçabilité.** AC84/AC86

**Dépendances explicites.** BH06, BH07, B29, B30

#### BH09 — Contrats et documentation des offres

**Commit proposé :** `docs(api): formaliser les contrats du coup de main`

**À réaliser.** Fusionner COUPS_DE_MAIN_API dans OpenAPI réel ; exposer can.offer/can.accept et erreurs ; documentation tables et enum ; tests schema/réponses ; commande génération types réutilisable pendant F.

**Critères de sortie.** Toutes routes P0 sont implémentées, pas uniquement décrites ; exemples cohérents avec CreateHelpRequestData actuel ; types protégés rejetés ; aucun YAML factice présenté testé.

**Traçabilité.** F18 ; AC69–AC87

**Dépendances explicites.** BH08

#### BH10 — Recette transactionnelle et privée des coups de main

**Commit proposé :** `test(help-offers): vérifier concurrence consentements et confidentialité`

**À réaliser.** Exécuter AC69–87 backend, tests réseau/idempotence/cron/suspension et transactions PostgreSQL ; vérifier non-régression F16/F17, questions sans code, lab inchangé ; documenter vrais résultats avant B39.

**Critères de sortie.** Courses réellement concurrentes et visibilité négative observées ; aucune preuve fabriquée ; tous nouveaux contrats intégrés au BACKEND_GATE ; charge réestimée.

**Traçabilité.** AC69–AC90 partie serveur

**Dépendances explicites.** BH02, BH09

#### B39 — Revue de sécurité API

**Commit proposé :** `test(security): couvrir les accès et entrées hostiles`

**À réaliser.** Compléter tests transversalement : mass assignment, IDOR, routes privées, limites de taille, liens non récupérés, Markdown/code inerte, session, CSRF, mots de passe et logs. Ajouts strictement nécessaires aux règles déjà définies. Atelier : couvrir également F13/F14/F15, les comparaisons réelles et AC33–AC52 ; P1 AC47 seulement si livré. Alignement mail : Inclure contrôle des parents projets, annuaire volontaire, champs publics et liens externes des F16/F17. Coup de main : Inclure F18 : IDOR offre, consentements, publication, états concurrents, quotas, logs et permissions du fil.

**Critères de sortie.** Aucun accès critique indu ; les tests ne désactivent pas discrètement le middleware évalué. Corriger les anomalies par petits commits de fix séparés au besoin. Alignement mail : AC53–64 négatifs couverts.

**Traçabilité.** AC02–AC07/AC18/AC25/AC26 ; F13–F15 ; AC33–AC52 selon phase ; ADR-005 / [M]

#### B40 — Contrat API complet

**Commit proposé :** `docs(api): vérifier les contrats de tous les endpoints P0`

**À réaliser.** Compléter OpenAPI, exemples fictifs, enums, champs, erreurs, pagination, capacités, limites et routes auth hors /api/v1. Tester la conformité des réponses. Préparer génération des types via un outil dev indépendant, sans créer la SPA. Atelier : couvrir également F13/F14/F15, les comparaisons réelles et AC33–AC52 ; P1 AC47 seulement si livré. Alignement mail : Inclure API projets/développeurs, intention ask_question, erreurs et capacités ; types générés synchronisés. Coup de main : Inclure COUPS_DE_MAIN_API et les types/erreurs F18 dans le contrat réel.

**Critères de sortie.** Toutes les opérations nécessaires aux écrans ont un contrat testé ; les routes nouvelles non listées initialement sont des précisions d’implémentation tracées, pas un changement métier implicite. Alignement mail : Aucun endpoint nouveau absent du contrat.

**Traçabilité.** F01–F12 ; N05 ; F13–F15 ; AC33–AC52 selon phase ; ADR-005 / [M]

#### B41 — Recette backend complète

**Commit proposé :** `test(backend): relier le parcours API et la concurrence`

**À réaliser.** Exécuter A demande → B proposition → A accepte → B révise la capsule → C teste/réutilise, par API. Tester B1-05 réellement concurrent et courses résolution/réouverture. Consolider fixtures et mesures API du cahier. Atelier : couvrir également F13/F14/F15, les comparaisons réelles et AC33–AC52 ; P1 AC47 seulement si livré. Alignement mail : Inclure AC53–64 serveur et questions sans lab. Coup de main : Réexécuter AC69–90 applicables au backend ; ne pas se limiter aux tests du laboratoire.

**Critères de sortie.** Rapport indique commit/environnement/commandes/observés. API couvre le parcours sans frontend ; ne pas déclarer AC32 navigateur réussi à ce stade. Alignement mail : Une réception antérieure ne couvre pas F16/F17.

**Traçabilité.** AC08–AC21 ; AC32 partie API ; N02 ; F13–F15 ; AC33–AC52 selon phase ; ADR-005 / [M]

#### B42 — Exploitation backend

**Commit proposé :** `chore(ops): préparer santé sauvegarde et reprise backend`

**À réaliser.** Runbook et scripts sûrs pour configuration, worker, cron, health minimal/protégé, sauvegarde chiffrée et restauration dédiée. Préparer la topologie Datacloud seulement sur capacités vérifiées. Déploiement retenu : Préparer exploitation d’un seul VPS : API, queues, runner distinct, PostgreSQL local, sauvegarde distante et health. Pas d’assets React servis ici pour l’application principale.

**Critères de sortie.** Exercice local documenté ; accès distant absent = BLOQUÉ pour ce contrôle. Aucune migration destructive production ni achat autorisé implicitement. Web et worker ont leurs configurations distinctes. Déploiement retenu : Runbook compatible Systalink ; secrets séparés ; aucun déploiement réel sans accord.

**Traçabilité.** AC29/AC30 partie backend ; N04 ; ADR-004 / DEP

#### B43 — Qodana backend

**Commit proposé :** `ci(qodana): contrôler le backend avec le profil validé`

**À réaliser.** Confirmer licence, linter, chemins et ce qui est envoyé au service ; installer à version/digest validé. Quality gate explicite, profil conservé, rapport réel lié au commit. Sans accès : alternative locale maintenue et décision humaine tracée. Déploiement retenu : Utiliser Ultimate déclaré existant. Configurer Qodana sur le vrai projet et l’offre ; audits licences/vulnérabilités restent indépendants lorsqu’ils ne sont pas compris dans Ultimate.

**Critères de sortie.** Une alerte témoin bloque effectivement le contrôle, puis est corrigée. Analyse indisponible affichée absente, jamais passée ; secrets Qodana uniquement dans environnement CI autorisé. Déploiement retenu : Token absent = contrôle non exécuté, pas vert ; ne pas annoncer les fonctions Ultimate Plus sans droit.

**Traçabilité.** AC28 ; ARB04 ; ADR-004 / DEP

#### B44 — Point de validation backend

**Commit proposé :** `docs(gate): consigner la réception technique du backend`

**À réaliser.** Renseigner BACKEND_GATE avec preuves de tous les P0 backend, contrôles locaux/CI, limites et questions externes. Livrer installation, API, tests et liste des endpoints. Demander la revue par un autre membre et GO_FRONTEND. Atelier : couvrir également F13/F14/F15, les comparaisons réelles et AC33–AC52 ; P1 AC47 seulement si livré. Déploiement retenu : Consigner la conformité de la configuration serveur et les tests locaux/CI du contrat CORS/session ; distinguer la recette de domaines réels reportée à la livraison. Alignement mail : Recevoir F01–F18, API projets/annuaire et questions sans code, avant GO_FRONTEND humain. Coup de main : La porte backend couvre F18 et BH10 : demander une nouvelle revue et un GO_FRONTEND humain ; aucun frontend nouveau avant validation.

**Critères de sortie.** Aucune case cochée par hypothèse, aucun placeholder fonctionnel, aucune route P0 seulement mockée. Ne commencer aucun code frontend tant que le point de validation et l’autorisation manquent. Déploiement retenu : Revue humaine et GO_FRONTEND nécessaires ; aucune conformité navigateur réelle déclarée avant son test. Alignement mail : Aucun passage sur l’ancienne réception seule ; preuves BC01–BC08 examinées.

**Traçabilité.** F01–F12 partie backend ; N01–N08 ; F13–F15 ; AC33–AC52 selon phase ; ADR-004 / DEP ; ADR-005 / [M]

**Dépendances explicites.** BH10

### Phase F — Frontend après GO_FRONTEND

#### F01 — Socle React

**Commit proposé :** `chore(frontend): initialiser React TypeScript et Vite`

**À réaliser.** Après GO_FRONTEND, initialiser en préservant AGENTS.md ; scripts lint/typecheck/test/build, alias, frontières imports et env validée. Choisir versions compatibles et auditées ; aucune variable secrète VITE_. Déploiement retenu : Préparer Vite pour Vercel, racine frontend, dist ; configuration reproductible sans secret public.

**Critères de sortie.** Build et test minimal passent ; CI frontend exécutable ; aucun backend reconstruit ou remplacé. Types stricts, pas de any généralisé. Déploiement retenu : Compilation du frontend après GO_FRONTEND uniquement.

**Traçabilité.** N05/N06 ; ADR-004 / DEP

#### F02 — Tokens de design

**Commit proposé :** `feat(design): installer la palette et la typographie HAAS`

**À réaliser.** Transposer docs/design/tokens dans le frontend, configurer thème clair, police système, grille, tailles et focus. Nom de marque configurable. Créer une page de catalogue de composants de développement, pas une dépendance Storybook imposée.

**Critères de sortie.** Toutes les combinaisons autorisées passent le script de contraste ; border décorative non utilisée comme seule délimitation d’un input. Pas de couleur brute répétée dans les écrans.

**Traçabilité.** AC31 ; N03

#### F03 — Composants de formulaire

**Commit proposé :** `feat(ui): créer des champs et boutons accessibles`

**À réaliser.** Button, LinkButton, Input, Textarea, Select, Checkbox, FormField, ErrorSummary et PasswordField. Labels visibles, descriptions, erreurs liées, focus et tailles 44 px. Masque de mot de passe réversible et collage autorisé.

**Critères de sortie.** Tests keyboard/name/label/disabled/pending/error ; données longues et zoom. Aucun placeholder servant seul d’étiquette.

**Traçabilité.** AC05/AC31

#### F04 — Composants de lecture

**Commit proposé :** `feat(ui): structurer cartes dialogues et états`

**À réaliser.** Badge sémantique, EmptyState, LoadingState, Alert, Dialog, Drawer, Tabs, Pagination et CodeBlock. Choisir primitives accessibles auditées si utiles ; ne pas cumuler deux bibliothèques de composants.

**Critères de sortie.** Dialogs piègent puis rendent le focus ; Escape et titre accessible ; code inerte et défilement interne ; boutons avec noms explicites ; états sans dépendance à couleur seule.

**Traçabilité.** AC07/AC31

#### F05 — Client API et types

**Commit proposé :** `feat(http): centraliser le transport et les erreurs API`

**À réaliser.** Axios unique, types générés depuis OpenAPI, ApiError, abort, timeout et callbacks injectés. Interceptors nettoyés et réessais maîtrisés ; auth/CSRF hors préfixe API. Fonctions API par feature. Déploiement retenu : Utiliser VITE_API_URL validée, origine API absolue, withCredentials et withXSRFToken. Les routes auth/CSRF n’incluent pas /api/v1.

**Critères de sortie.** Tests 401/403/409/419/422/429/503/réseau/annulation ; aucun redirect infini ni POST rejoué aveuglément ; un 503 de /me ne déconnecte pas. Déploiement retenu : Aucun appel principal à baseURL=/ sur Vercel ; tests 401/419/422/429 et chemins auth/API.

**Traçabilité.** AC05/AC06 ; N01 ; ADR-004 / DEP

#### F06 — Session et guards

**Commit proposé :** `feat(auth): contrôler les écrans avec la session serveur`

**À réaliser.** QueryProvider, source session unique, Auth/Guest/VerifiedEmail/ActiveAccount/PermissionGuard. Capabilities calculées par le backend pour les actions. Purge caches privés et annulation sur changement d’identité. Déploiement retenu : Prendre en charge cookies inter-origines same-site et états de session distincts réseau/anonyme.

**Critères de sortie.** Anonyme/chargement/erreur réseau/connecté distincts ; deep links et retour après login sûrs ; session A ne fuit pas vers B ; aucune confiance dans rôle local. Déploiement retenu : Logout purge les caches privés ; un 503 ne devient pas une déconnexion.

**Traçabilité.** AC02/AC03/AC26 ; ADR-004 / DEP

#### F07 — Navigation responsive

**Commit proposé :** `feat(layout): simplifier la navigation publique et membre`

**À réaliser.** PublicLayout/MemberLayout/AdminLayout ; trois accès principaux Aider/Explorer/Mon espace plus action Demander de l’aide. Navigation mobile avec libellés ; skip link, page title, focus après navigation et retour navigateur conservé. Alignement mail : Navigation principale Explorer/Projets/Développeurs ; action Demander de l’aide ; Mon espace et contributions dans compte.

**Critères de sortie.** Utilisable à 320/360/390/768/1280 px et clavier ; pas de sidebar permanent sur petit écran ; aucun élément collant ne masque le focus. Alignement mail : Menu mobile lisible ; pas de laboratoire CTA principal.

**Traçabilité.** AC31 ; N03 ; ADR-005 / [M]

#### F08 — Pages de compte

**Commit proposé :** `feat(auth): relier inscription connexion et récupération`

**À réaliser.** Pages réelles login/register/verification/forgot/reset, messages français, caps lock utile si disponible, erreurs inline et lien retour sûr. Écran de compte suspendu informatif.

**Critères de sortie.** Parcours contre Sanctum réel, CSRF inclus ; compte non vérifié guidé ; messages sans révélation de compte au reset ; aucun faux succès local.

**Traçabilité.** AC01/AC02/AC26

#### F09 — Accueil utile

**Commit proposé :** `feat(home): présenter l’entraide sans surcharge`

**À réaliser.** Promesse courte, CTA Demander de l’aide/Explorer les solutions, explication Demander→Collaborer→Vérifier→Partager et trois contenus utiles réels/éditoriaux étiquetés. Pas de faux compteur ni de carrousel. Atelier : intégrer help_intent sans multiplier les workflows ; accueillir une piste déjà produite avec ou sans IA. Alignement mail : Promesse communautaire ; aperçus de projets, échanges et solutions ; lien vers les développeurs ; pas de faux comptes. Coup de main : L’accueil comporte les deux entrées Faire avancer mon projet / Donner un coup de main, sans bloquer explorer ou question.

**Critères de sortie.** Un visiteur comprend les deux actions avant connexion ; liens reliés à des contenus disponibles ; empty/error/load states travaillés. Alignement mail : Présentation de projet possible depuis accueil ; atelier expliqué en secondaire.

**Traçabilité.** F02/F12 ; AC27/AC31 ; ADR-005 / [M]

#### F10 — Catalogue

**Commit proposé :** `feat(explorer): rechercher les demandes et solutions`

**À réaliser.** Onglets clairs demandes/capsules, recherche et filtres visibles/supprimables avec URL synchronisée. Pagination explicite, cartes lisibles, état sans résultat guidé. Utiliser l’API réelle.

**Critères de sortie.** Rechargement conserve les filtres ; recherche n’expose pas retirés/brouillons ; raccourcis non nécessaires ; mobile sans défilement horizontal de page.

**Traçabilité.** AC15/AC25/AC31

#### F11 — Formulaire de demande

**Commit proposé :** `feat(requests): guider la publication en trois étapes`

**À réaliser.** Contexte → Problème → Vérification ; RHF/Zod depuis contrat, libellés et exemples du screen spec. Brouillon privé, résumé avant publication, alerte de secrets. Sauvegarde en mémoire sauf action explicite vers serveur. Alignement mail : Mode ask_question sans champs panne obligatoires ; lien projet facultatif prérempli pour propriétaire.

**Critères de sortie.** Erreurs sous champs, focus première erreur, précédent sans perte ; création/édition connectées ; expiration/réseau ne détruisent pas la saisie ni ne promettent une sauvegarde inexistante. Alignement mail : Question simple réalisable sans code/lab ; erreurs accessibles.

**Traçabilité.** AC05/AC06/AC07/AC31 ; ADR-005 / [M]

#### F12 — Discussion de demande

**Commit proposé :** `feat(collaboration): afficher les échanges et propositions`

**À réaliser.** Détail demande, contexte repliable, discussion et propositions distinctes, panneau de progression. Commenter/éditer avec ownership ; polling léger uniquement visible/en ligne ; historique lisible.

**Critères de sortie.** Création idempotente, suppression/modération bien interprétée ; commentaire ne prétend pas résoudre ; accès interdit et contenu retiré gérés.

**Traçabilité.** AC08/AC25/AC31

#### F13 — Résolution guidée

**Commit proposé :** `feat(resolutions): confirmer et rouvrir une solution`

**À réaliser.** Formulaire de proposition, note d’acceptation, self-resolution signalée, non-retenue et réouverture avec motif. Afficher actions selon can du serveur ; attendre réponse pour succès.

**Critères de sortie.** Conflit 409 conserve la saisie et propose rechargement ; double clic sans effet supplémentaire ; admin non auteur ne voit pas un faux pouvoir de validation.

**Traçabilité.** AC09/AC10/AC11

#### F14 — Éditeur de capsule

**Commit proposé :** `feat(capsules): documenter les versions issues de l’aide`

**À réaliser.** Création depuis demande résolue ou origine éditoriale habilitée, champs procédure/limites/provenance/versions, contributeurs et aperçu. Soumission à revue avec état visible.

**Critères de sortie.** Brouillon privé ; auteur comprend ce qui manque ; pas de mélange entre capsule, brique et laboratoire ; pas d’édition du texte d’une version publiée.

**Traçabilité.** AC12/AC13

#### F15 — Lecture et preuves de capsule

**Commit proposé :** `feat(capsules): rendre les versions et preuves compréhensibles`

**À réaliser.** Page capsule avec sélection de version, diagnostic/correctif/procédure/limites, attribution et panneau de preuves. Distinguer accepté/retour déclaré/test machine. Téléchargement contrôlé et état droits non validés explicite.

**Critères de sortie.** Badge lié à la version choisie ; retrait désactive actions avec motif ; code copiable en sécurité ; aucun slogan « code sécurisé » pour un seul test.

**Traçabilité.** AC13/AC14/AC15/AC25

#### F16 — Lancement et rapport du laboratoire

**Commit proposé :** `feat(lab): suivre les tests et afficher les résultats réels`

**À réaliser.** Lancer B1, gérer 202/queued/running et résultats terminaux, quota et délai. Polling stoppé à la fin ou hors écran ; nouvelle intention nouvelle clé. Afficher attendu/observé, date/version/digests, limites.

**Critères de sortie.** Failed/error/timed_out distincts ; aucun pourcentage inventé ; run interrompu réconcilié ; seuls B1-01 à 04 rejouables web, B1-05 étiqueté CI.

**Traçabilité.** AC16–AC21

#### F17 — Espace personnel et profils

**Commit proposé :** `feat(profiles): organiser les demandes et contributions`

**À réaliser.** Mon espace avec brouillons/demandes/notifications/favoris et liens vers contributions. Profil public sans faux score ni données privées ; édition des champs autorisés. Alignement mail : Afficher Mes projets et lien vers les préférences annuaire ; profils listent uniquement leurs projets publics visibles.

**Critères de sortie.** Premier usage guidé ; compteurs de démo distingués ; état vide utile ; inaccessible aux autres lorsque privé. Alignement mail : Aucun brouillon ni état de session d’un autre membre.

**Traçabilité.** F11 ; AC27/AC31 ; ADR-005 / [M]

#### F18 — Favoris et retours

**Commit proposé :** `feat(reuse): recueillir des retours de réutilisation précis`

**À réaliser.** Ajouter/retirer favoris et déposer un retour sur la version utilisée : environnement, date, reproduit/partiellement/non reproduit et observation. Afficher origine interne lorsque pertinente.

**Critères de sortie.** Un seul retour actif, édition historisée ; favoris privés ; le libellé ne transforme pas une déclaration en certification.

**Traçabilité.** AC14 ; F07

#### F19 — Notifications

**Commit proposé :** `feat(notifications): présenter les événements utiles`

**À réaliser.** Panneau/listes paginées, lu/non lu, liens profonds et résumé sans secret. Sur ressource retirée, afficher un état sûr. Pas de toast en rafale à chaque polling.

**Critères de sortie.** Lecture unique et compteur serveur cohérents ; cache purgé à la sortie ; navigation clavier/petits écrans vérifiée.

**Traçabilité.** F10 ; AC25/AC31

#### F20 — Back-office

**Commit proposé :** `feat(moderation): traiter les revues et signalements`

**À réaliser.** Files de revue capsule, signalements, comptes et lab incidents ; filtres simples ; dialogues avec motif. Publication indépendante, correction, retrait et suspension via APIs existantes. Alignement mail : Inclure signalements de projet ; ne pas ajouter un back-office projet indépendant.

**Critères de sortie.** Un membre n’accède pas aux écrans admin ni aux données sous-jacentes ; un admin auteur ne se relit pas ; actions dangereuses nommées, pas de bouton générique « OK ». Alignement mail : Propagation de masquage visible et auditée.

**Traçabilité.** AC12/AC25/AC26 ; ADR-005 / [M]

#### F21 — Démonstrateur B2

**Commit proposé :** `feat(b2): conserver les commandes fictives hors connexion`

**À réaliser.** Mini React isolé sous modules/B2-offline-form ; IndexedDB pour commandes fictives et clé stable, service worker de shell limité à cette origine. États local/envoi/confirmé/à reprendre ; synchronisation manuelle fiable. Déploiement retenu : Déployer B2 dans un projet Vercel distinct, demo.example.com, API demo-api.example.com cookie-free ; uniquement données fictives.

**Critères de sortie.** Vraie coupure puis fermeture/réouverture retrouve la saisie si stockage intact. Effacement/stockage indisponible expliqué ; aucun cookie/cache HAAS inclus. Déploiement retenu : Aucun cookie du parent .haas.example.com envoyé à B2 ; aucune persistance hors connexion de la plateforme principale.

**Traçabilité.** AC22/AC23/AC24 ; ADR-004 / DEP

#### FC01 — Catalogue et fiche projet

**Commit proposé :** `feat(projects): présenter les projets et leurs échanges`

**À réaliser.** Créer features/projects et UX18 : listes/détails reliés API, filtres URL, propriétaire/capacités, liens externes sûrs, états vide/erreur ; actions vers demandes existantes.

**Critères de sortie.** API réelle, aucun bouton mort ni faux compteur ; mobile/clavier, masquage et 403/404 ; état du projet affiché déclaré.

**Traçabilité.** F16 ; UX18 ; AC55/AC59/AC65/AC66

#### FC02 — Édition de projet et demande liée

**Commit proposé :** `feat(projects): publier une fiche et demander de l’aide`

**À réaliser.** Implémenter UX19, formulaire de projet, publication/archivage, brouillon et lien prérempli vers demande avec project_id ; reprise 409/422 sans pertes ; ajouter Mes projets au compte.

**Critères de sortie.** Créer→publier→demander sur API réelle ; owner/tiers, conflit et idempotence ; données de démonstration identifiées ; cache session vidé.

**Traçabilité.** F16 ; UX19 ; AC53–58/AC65/AC66

#### FC03 — Découverte et préférences des membres

**Commit proposé :** `feat(developers): découvrir les membres par technologie`

**À réaliser.** Créer features/developers et UX20 ; annuaire filtrable, profil et projets/contributions publiques ; opt-in et disponibilité dans préférences compte ; aucune messagerie ni faux indicateur online.

**Critères de sortie.** AC60–63 côté navigateur, clavier/mobile, cache après opt-out, formulaire et labels explicites ; aucun appel Axios en JSX.

**Traçabilité.** F17 ; UX20 ; AC60–63/AC65/AC66

#### FH01 — Accueil et occasions concrètes d’aider

**Commit proposé :** `feat(discovery): ouvrir le parcours donner un coup de main`

**À réaliser.** Après GO_FRONTEND : UX21 deux entrées plus accès explorer/question ; /coups-de-main réel avec filtres/résultats et explication de correspondance, CTA direct vers fil ou offre selon cas.

**Critères de sortie.** Pas de faux besoins ; loading/vide/erreur séparés ; pas d’annuaire imposé ; URL filtres et mobile/clavier.

**Traçabilité.** UX21 ; AC71/AC72/AC87

**Dépendances explicites.** B44, BH10, F05, F07, FC01

#### FH02 — Ouvrir son projet volontairement

**Commit proposé :** `feat(projects): configurer une demande de coup de main`

**À réaliser.** Modifier UX19 et préférences ; switch non précoché, catégories, help_sought requis seulement pour ouverture ; prévisualisation, état fermé explicite et fermeture confirmée ; accès toujours propriétaire.

**Critères de sortie.** Aucun opt-in induit par publication ; consentement annuaire séparé ; 409/422 conserve saisie en mémoire ; fermer ne masque pas les anciens fils.

**Traçabilité.** AC69/AC70/AC82 ; UX19/UX21

**Dépendances explicites.** FH01, FC02

#### FH03 — Proposition et aperçu de publication

**Commit proposé :** `feat(help-offers): proposer une aide avec aperçu lisible`

**À réaliser.** UX22 + api/models/schemas/hooks centralisés ; formulaire catégorie/résumé/résultat, consentement public explicite ; confirmation pending privée ; retour connexion sûr sans secret persistant.

**Critères de sortie.** Libellés compréhensibles ; pas de soumission sans consentement ; erreur réseau réconciliée même clé ; succès API seulement ; captures mobile/clavier.

**Traçabilité.** UX22 ; AC73/AC75/AC88/AC89

**Dépendances explicites.** FH02, F06

#### FH04 — Offres reçues envoyées et décision

**Commit proposé :** `feat(help-offers): accepter ou décliner une aide clairement`

**À réaliser.** UX23 listes privées, détail et choix explicite create/attach ; préremplissage editable côté propriétaire, résumé proposant figé ; double consentement, états finaux, retrait et expiration.

**Critères de sortie.** Pas d’acceptation optimiste ; 409 relit sans inventer action ; 403/404 ne fuit pas existence ; logout purge ; notifications distinctes de chat.

**Traçabilité.** UX23 ; AC77–AC85/AC88/AC89

**Dépendances explicites.** FH03, F19

#### FV201 — Formulaire et suivi des cas

**Commit proposé :** `feat(cases-ui): guider la proposition d’un cas concret`

**À réaliser.** Feature verification-cases : API/types/schémas/hooks/pages/composants. Intention d’aide ciblée, états draft/submitted/reviewed et explication non exécuté ; réutiliser auth et design.

**Critères de sortie.** API Laravel réelle, labels et erreurs liés, formulaire préservé en mémoire ; aucun statut optimiste « testé ».

**Traçabilité.** F13 ; AC33–AC37 frontend

#### FV202 — Choisir et lancer une comparaison

**Commit proposé :** `feat(comparison-ui): choisir un scénario approuvé`

**À réaliser.** Feature comparisons : profil/versions non libres, choix scénario fixe, aperçu des deux côtés, indication quota, clé par intention et suivi 202.

**Critères de sortie.** Rejeux/409/429/réseau testés ; profils retirés absents ; annonce clair du coût de deux unités.

**Traçabilité.** F14 ; AC38/AC41/AC42

#### FV203 — Lecture comparative accessible

**Commit proposé :** `feat(comparison-ui): montrer les observations avant et après`

**À réaliser.** ComparisonReportPage/Cards : expected/observed par côté, trois états techniques distincts, cinq conclusions, dates et détail de preuve. Polling borné/focus, historique daté.

**Critères de sortie.** Mobile une colonne, clavier, partiel/error/timeout/régression visibles ; aucune victoire présumée ni indicateur purement coloré.

**Traçabilité.** F14 ; AC40/AC43/AC44/AC48/AC49

#### FV204 — Fiche de vérification et mémoire

**Commit proposé :** `feat(evidence-ui): expliquer les preuves et leurs contributeurs`

**À réaliser.** VerificationSummaryPanel et timeline de contributions. Types de preuves nommés, limites lisibles. P1 copie de contexte uniquement après P0 reçu, aperçu/annulation/succès réel, pas d’API IA.

**Critères de sortie.** Version sélectionnée exacte ; absent/retiré géré ; P1 absent implique aucun bouton. Si livré, AC47 entièrement exécuté.

**Traçabilité.** F15 ; AC45–AC47

#### FV205 — Recette visuelle et bout en bout atelier

**Commit proposé :** `test(workshop-ui): vérifier les cas et comparaisons en situation`

**À réaliser.** Playwright contre API réelle + revue manuelle mobile/clavier/contrastes et états réseau. Données fictives explicitement séparées du pilote externe.

**Critères de sortie.** Captures/rapports du commit courant ; AC48–AC50 réels ; aucun contrôle auto ne vaut revue humaine complète.

**Traçabilité.** F13–F15 ; AC48–AC52

#### FC04 — Parcours communautaire complet

**Commit proposé :** `test(community): valider la découverte et les échanges de bout en bout`

**À réaliser.** Relier accueil/navigation à projets/développeurs ; mode Poser une question dans UX05 sans code ni résultat imposé. Exécuter A→projet→B→aide→capsule→C et une question sans labo ; vérifier textes/états et capture datée.

**Critères de sortie.** AC64–68 exécutés avec API réelle ; lecteur extérieur comprend comment participer ; l’absence de laboratoire ne bloque aucune contribution ordinaire. Pas de chiffres d’adoption inventés.

**Traçabilité.** F02/F03/F16/F17 ; UX01/UX05/UX18–20 ; AC64–68

#### FH05 — Progrès visibles et recette navigateur

**Commit proposé :** `test(collaboration): vérifier la première aide de bout en bout`

**À réaliser.** Vue progrès du projet et projection dans fil ; Playwright projet sans fil→offre→acceptation→apport→résolution éventuelle ; route attach et refus/retrait ; revue du texte, focus, version mobile et zoom.

**Critères de sortie.** AC86–90 observés ; accepté≠travail réalisé ; sans laboratoire obligatoire ; traces rapportées au commit réel, aucun faux usage ou résultat.

**Traçabilité.** AC86–AC90 ; UX18/UX21–23

**Dépendances explicites.** FH04, F13, FC04

#### F22 — Résilience frontend

**Commit proposé :** `test(resilience): vérifier les reprises et caches privés`

**À réaliser.** Tests d’intégration frontend sur timeout, 419, résultat serveur perdu, invalidation et passage utilisateur A→B. Corriger les hooks et interceptors au besoin en commits ciblés. Coup de main : Vérifier les offres pending privées, reprise idempotente et purge de cache après changement de compte.

**Critères de sortie.** Aucune duplication de mutation, fuite cache, re-login infini ou faux accusé serveur. Boutons de reprise présents et effets réellement vérifiés en API.

**Traçabilité.** AC06/AC23/AC26

#### F23 — Audit d’accessibilité

**Commit proposé :** `test(a11y): vérifier clavier focus et contrastes`

**À réaliser.** Automatisation Playwright/axe selon licences validées, plus clavier manuel, labels, reflow, zoom, mouvement réduit, lecteurs d’écran disponibles. Faire le contrôle de contraste des états hover/focus/error et des blocs de code. Alignement mail : Inclure UX18–UX20 dans la revue clavier/mobile/contraste/zoom. Coup de main : Vérifier les consentements et UX21–23 au clavier, à 360/390 px et au zoom.

**Critères de sortie.** Pas de violation connue non traitée sur parcours central ; capturer limites des tests manuels. Audit automatique seul ne vaut pas conformité globale. Alignement mail : AC65 et AC66 sur nouveaux écrans.

**Traçabilité.** AC31 ; N03 ; ADR-005 / [M]

#### F24 — Finition visuelle

**Commit proposé :** `style(ui): harmoniser les écrans sur le design system`

**À réaliser.** Revue dans navigateur exécuté à 360,390,768,1280 px : hiérarchie, espaces, lisibilité, formulaires longs, contenus vides/erreurs et alignement. Corriger les incohérences sans changer la charte page par page. Alignement mail : Revoir la cohérence communauté-first ; mêmes tokens, textes et composants sur projets et développeurs.

**Critères de sortie.** Captures datées du build réel, revue de l’autre membre ; pas de retouche d’image pour masquer un défaut produit ; les actions restent accessibles à 320 px. Alignement mail : Aucun maquettage joli non relié à l’API.

**Traçabilité.** AC31 ; N03 ; ADR-005 / [M]

#### F25 — Recette navigateur complète

**Commit proposé :** `test(e2e): vérifier le parcours HAAS contre Laravel`

**À réaliser.** Tests A→B→A→réviseur→C, demande/capsule/B1/retour, plus B2 coupure/reprise, mobile et modération. Exécuter le build servi comme en production, pas seulement Vite en développement. Atelier : couvrir également F13/F14/F15, les comparaisons réelles et AC33–AC52 ; P1 AC47 seulement si livré. Déploiement retenu : Recette sur deux origines compatibles : login/CSRF, lecture, écriture, déconnexion, erreurs et parcours complet. Pas de wildcard pour previews. Alignement mail : Exécuter AC53–AC68 et routes communauté avec backend réel ; joindre preuves. Coup de main : Inclure le parcours AC90 réel avec création et rattachement du fil, refus et retour aux demandes classiques.

**Critères de sortie.** AC32 prouvé sur API réelle et utilisateurs distincts ; mesure 4 Mbit/s/150 ms et build budget ; tests négatifs et droits exécutés ; mocks confinés aux tests composants. Déploiement retenu : Test navigateur du vrai flux requis ; une preview visuelle ou des mocks ne valident pas l’API. Alignement mail : Le parcours question/discussion fonctionne sans laboratoire.

**Traçabilité.** AC01–AC32 selon matrice ; N02 ; F13–F15 ; AC33–AC52 selon phase ; ADR-004 / DEP ; ADR-005 / [M]

#### F26 — Point de validation frontend

**Commit proposé :** `docs(gate): consigner la recette frontend et UX`

**À réaliser.** Mettre à jour FRONTEND_GATE, matrice AC, captures et limitations ; relire toute la microcopie. Confirmer absence d’écran non relié, faux contenu d’adoption et appels HTTP dispersés. Atelier : couvrir également F13/F14/F15, les comparaisons réelles et AC33–AC52 ; P1 AC47 seulement si livré. Déploiement retenu : Consigner toutes les vues liées à la vraie API et les vérifications de cookies/CORS en environnement de recette. Alignement mail : Recevoir UX01–UX20 et F16/F17, formulaires/questions et contributions avant release. Coup de main : Couvrir UX21–23 et FH05 avec preuve réelle ; une ancienne recette ne couvre pas F18.

**Critères de sortie.** Contrôles frontend et Qodana applicable réels ; preuves intégration/UX liées au commit ; revue humaine distincte. Ne pas appeler « production » un build seulement local. Déploiement retenu : Les tests DNS/TLS finaux restent dans DEPLOYMENT_GATE, jamais marqués passés par déduction. Alignement mail : Vingt familles, preuves datées réelles et aucun bug bloquant.

**Traçabilité.** F01–F12 ; AC31/AC32 ; F13–F15 ; AC33–AC52 selon phase ; ADR-004 / DEP ; ADR-005 / [M]

**Dépendances explicites.** FH05

### Phase R — Réception et livraison

#### R01 — Inventaires et droits

**Commit proposé :** `docs(compliance): inventorier dépendances droits et usage IA`

**À réaliser.** Consolider versions, licences directes/transitives/runtime/outillage, provenance du code/visuels/skills et déclarations IA. Séparer propres droits, droits tiers, contributions de testeurs et diffusion contrôlée des kits. Déploiement retenu : Vérifier plan Vercel, dépôts privés et sièges avec les vrais auteurs Git ; Ultimate existant et couverture du projet.

**Critères de sortie.** Aucun composant inconnu accepté sans décision, aucune licence publique appliquée au projet pour convenance. ARB de concours non résolu apparaît en blocage explicite. Déploiement retenu : Aucune publication du dépôt ni falsification des auteurs pour contourner des conditions.

**Traçabilité.** N06/N07 ; ARB01–ARB06 ; ADR-004 / DEP

#### R02 — Artefact reproductible

**Commit proposé :** `ci(release): construire un artefact lié au commit testé`

**À réaliser.** Builder backend/frontend et kits aux versions verrouillées, manifeste/digests et contrôles requis. GitHub environment et protections disponibles vérifiés ; approbation indépendante avant action distante. Déploiement retenu : Construire les artefacts API/runner et frontend, chacun avec empreinte, SHA et configuration cible ; manifeste de versions liées.

**Critères de sortie.** Aucun binaire généré hors pipeline présenté comme testé ; secrets exclus ; job requis absent ne permet pas un déploiement silencieux. Déploiement retenu : Aucune reconstruction silencieuse différente ; les rapports correspondent aux artefacts publiés.

**Traçabilité.** AC28 ; N05 ; ADR-004 / DEP

#### R03 — Recette et livraison Systalink / Vercel

**Commit proposé :** `ci(deploy): livrer l’API Systalink puis React Vercel`

**À réaliser.** Après autorisation d’accès/déploiement, configurer React sur Vercel et API/auth sur Systalink, B2 isolé, cookies bornés au parent de confiance, worker/cron, santé et alertes. Effectuer migrations compatibles et smoke tests. Déploiement retenu : Recette locale/CI et test contrôlé des domaines réels ; accord GO_PRODUCTION ; livrer API Systalink puis React Vercel, de façon compatible et contrôlée. Pas de préproduction distante permanente imposée.

**Critères de sortie.** URL réellement accessible, /api inconnue JSON, CSP/session/caches contrôlés, isolation lab vérifiée. Pas de supposition de capacité, achat ou transfert de secret. Déploiement retenu : Vercel ne publie pas main avant les contrôles ; TLS, cookies, CORS, liens profonds et sous-lots DEP validés.

**Traçabilité.** N01/N04 ; F08 ; ADR-004 / DEP

#### R04 — Restauration et retour stable

**Commit proposé :** `test(ops): vérifier restauration et retour au build stable`

**À réaliser.** Restaurer sauvegarde chiffrée sur environnement séparé ; mesurer temps/perte. Tester retour au code précédent sans rollback destructif automatique de migrations ; procédures lisibles par l’autre membre. Déploiement retenu : Restaurer une sauvegarde chiffrée hors production. Tester rollback app/backend/frontend compatible et redémarrage des workers.

**Critères de sortie.** AC29/AC30 avec horodatage et résultat réel ; sauvegarde seule n’est pas restauration ; aucune donnée de production supprimée par le test. Déploiement retenu : Ni migration destructive ni effacement de production ; durées et pertes cibles mesurées.

**Traçabilité.** AC29/AC30 ; N04 ; ADR-004 / DEP

#### RV201 — Preuve d’utilité collaborative

**Commit proposé :** `docs(pilot): documenter l’apport d’un cas extérieur`

**À réaliser.** Faire proposer ou reproduire un cas par un testeur extérieur consentant puis observer réutilisation. Séparer ce pilote du scénario éditorial préparé ; consigner erreurs et limites.

**Critères de sortie.** Contributions attestées ou objectif non atteint signalé ; pas de noms/mesures inventés ; pitch actualisé au périmètre livré.

**Traçabilité.** F13–F15 ; AC46/AC50 ; pilote

#### RH01 — Observer les premières collaborations

**Commit proposé :** `docs(pilote): documenter les coups de main réellement utiles`

**À réaliser.** Pilote volontaire après recette : cible qualitative trois petits coups de main ; noter absence de réponse/refus et bénéfice sans orchestrer tous les clics ; consentement présentation, exemples fictifs séparés.

**Critères de sortie.** Objectif atteint ou non explicitement indiqué ; pas de données inventées ; vérifier compréhension publication et distinguer offre acceptée/apport utile ; pitch cohérent avec logiciel livré.

**Traçabilité.** F18 ; AC90 ; pilote

**Dépendances explicites.** FH05, RV201

#### R05 — Pilote et démonstration

**Commit proposé :** `docs(demo): préparer un scénario fondé sur des preuves`

**À réaliser.** Préparer données fictives étiquetées, comptes limités et scénario quatre minutes. Conduire les tests utilisateurs possibles, enregistrer observations brutes et correctifs. La vidéo de secours porte date/commit. Alignement mail : Pilote : un testeur découvre un projet/un membre et contribue ; observation de réutilisation séparée de la démo éditoriale. Présentation commence par le mail et la communauté.

**Critères de sortie.** Aucune métrique d’adoption fabriquée ; texte/pitch cohérents avec fonctionnalités livrées ; chaque personne peut expliquer architecture, sécurité et contribution de l’IA. Alignement mail : Aucune mesure inventée ; schémas remplacés seulement par captures du produit réellement livré.

**Traçabilité.** AC27/AC32 ; N07/N08 ; ADR-005 / [M]

#### R06 — Réception et transmission

**Commit proposé :** `docs(release): finaliser la réception et le dossier de reprise`

**À réaliser.** Renseigner RELEASE_GATE, release notes, manuel, runbook, contrats, kits approuvés, matrice et registre. Préparer tag après validation humaine, maintien de service et rappel individuel du vote selon calendrier confirmé. Atelier : couvrir également F13/F14/F15, les comparaisons réelles et AC33–AC52 ; P1 AC47 seulement si livré. Déploiement retenu : Rendre URL frontend et API, référence produit Datacloud actif, manifeste, contrôle de restauration, rapports Qodana réels et points ouverts. Alignement mail : Remettre cahier, contrats et instructions communauté ; vérifier cohérence du périmètre et noms sans suffixe de version. Coup de main : Livrer F18 avec AC69–90, journal de décisions et preuves de consentement ; pas de collaboration fictive annoncée réelle.

**Critères de sortie.** Les 90 AC (AC47 non applicable si P1 absent) ont une preuve ou un écart explicitement bloquant/accepté selon nature. Aucun défaut de sécurité ou faux rapport dérogeable. Aucune victoire ni conformité juridique garantie. Déploiement retenu : Les documents concordent avec l’infrastructure réellement livrée ; aucun badge vert fictif. Alignement mail : F01–F18, AC01–AC90 (AC47 conditionnel), dépôt et démo décrivent le même produit.

**Traçabilité.** F01–F12 ; AC01–AC32 ; N01–N08 ; F13–F15 ; AC33–AC52 selon phase ; ADR-004 / DEP ; ADR-005 / [M]

**Dépendances explicites.** RH01
