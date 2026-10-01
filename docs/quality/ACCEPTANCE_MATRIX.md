# HAAS — Matrice de réception AC01–AC52

Le texte « Attendu du cahier » est repris des tableaux source sans changement de sens. Les colonnes de phases sont des compléments de planification : un AC peut nécessiter plusieurs preuves. Tout commence NON EXÉCUTÉ. Un test API ne suffit pas à valider une exigence d’affichage.

| ID | Attendu du cahier | Phases / portée | État / preuve |
|---|---|---|---|
| AC01 | Inscription valide : compte créé, courriel privé, lien de vérification utilisable une fois selon son état. | Backend/API ou support serveur ; Frontend/navigateur | NON EXÉCUTÉ — aucune preuve |
| AC02 | Compte non vérifié : lecture autorisée, publication et laboratoire refusés. | Backend/API ou support serveur ; Frontend/navigateur | NON EXÉCUTÉ — aucune preuve |
| AC03 | Un membre tente de modifier une demande d’autrui : 403/404, aucune mutation. | Backend/API ou support serveur ; Frontend/navigateur | NON EXÉCUTÉ — aucune preuve |
| AC04 | Le client envoie role=admin ou author_id tiers : rejet, aucune élévation. | Backend/API ou support serveur | NON EXÉCUTÉ — aucune preuve |
| AC05 | Champ obligatoire absent : 422, erreur liée au champ, saisie conservée. | Backend/API ou support serveur ; Frontend/navigateur | NON EXÉCUTÉ — aucune preuve |
| AC06 | Double soumission avec même clé : une seule demande, même résultat. | Backend/API ou support serveur ; Frontend/navigateur | NON EXÉCUTÉ — aucune preuve |
| AC07 | Extrait contenant HTML/script : texte affiché sans exécution ; alerte de secret si motif détecté. | Backend/API ou support serveur ; Frontend/navigateur | NON EXÉCUTÉ — aucune preuve |
| AC08 | Une proposition structurée est publiée : demande en cours, notification unique. | Backend/API ou support serveur ; Frontend/navigateur | NON EXÉCUTÉ — aucune preuve |
| AC09 | Une personne non auteur accepte : refus ; l’auteur accepte : résolution créée. | Backend/API ou support serveur ; Frontend/navigateur | NON EXÉCUTÉ — aucune preuve |
| AC10 | Deux acceptations concurrentes : une seule résolution active. | Backend/API ou support serveur | NON EXÉCUTÉ — aucune preuve |
| AC11 | Réouverture motivée : historique conservé, capsule liée signalée à revoir. | Backend/API ou support serveur ; Frontend/navigateur | NON EXÉCUTÉ — aucune preuve |
| AC12 | Auteur tente sa propre publication éditoriale : revue indépendante requise. | Backend/API ou support serveur ; Frontend/navigateur | NON EXÉCUTÉ — aucune preuve |
| AC13 | Correction d’une version publiée : nouvelle version ; ancien rapport inchangé. | Backend/API ou support serveur ; Frontend/navigateur | NON EXÉCUTÉ — aucune preuve |
| AC14 | Retour humain attaché à une version : visible comme déclaration, pas résultat machine. | Backend/API ou support serveur ; Frontend/navigateur | NON EXÉCUTÉ — aucune preuve |
| AC15 | Version retirée : absente des téléchargements et recherche normale, motif disponible si autorisé. | Backend/API ou support serveur ; Frontend/navigateur | NON EXÉCUTÉ — aucune preuve |
| AC16 | B1 rejoué : assertions recalculées, identifiant neuf et digests exacts. | Backend/API ou support serveur ; Frontend/navigateur ; Intégration/exploitation | NON EXÉCUTÉ — aucune preuve |
| AC17 | Un scénario connu échoue : état Échouée, attendu/observé conservés. | Backend/API ou support serveur ; Frontend/navigateur | NON EXÉCUTÉ — aucune preuve |
| AC18 | Code ou commande envoyé au laboratoire : 422, aucune exécution. | Backend/API ou support serveur | NON EXÉCUTÉ — aucune preuve |
| AC19 | Quota ou concurrence dépassé : 429 ou mise en file bornée ; pas de saturation. | Backend/API ou support serveur ; Frontend/navigateur ; Intégration/exploitation | NON EXÉCUTÉ — aucune preuve |
| AC20 | Worker interrompu : état Erreur/Délai dépassé après contrôle ; jamais un faux succès. | Backend/API ou support serveur ; Frontend/navigateur ; Intégration/exploitation | NON EXÉCUTÉ — aucune preuve |
| AC21 | Deux utilisateurs lancent un test : aucune donnée de fixture partagée. | Backend/API ou support serveur ; Intégration/exploitation | NON EXÉCUTÉ — aucune preuve |
| AC22 | B2 hors connexion : enregistrement local explicite ; aucun accusé serveur inventé. | Frontend/navigateur ; Intégration/exploitation | NON EXÉCUTÉ — aucune preuve |
| AC23 | B2 répète l’envoi après reprise : une seule commande de démonstration. | Backend/API ou support serveur ; Frontend/navigateur ; Intégration/exploitation | NON EXÉCUTÉ — aucune preuve |
| AC24 | Stockage local B2 indisponible : avertissement, aucune garantie trompeuse de sauvegarde. | Frontend/navigateur ; Intégration/exploitation | NON EXÉCUTÉ — aucune preuve |
| AC25 | Contenu masqué : inaccessible via URL directe, recherche et ancien lien de kit. | Backend/API ou support serveur ; Frontend/navigateur ; Intégration/exploitation | NON EXÉCUTÉ — aucune preuve |
| AC26 | Compte suspendu : sessions révoquées, actions interdites. | Backend/API ou support serveur ; Frontend/navigateur ; Intégration/exploitation | NON EXÉCUTÉ — aucune preuve |
| AC27 | Jeu de démonstration : exclu des indicateurs d’usage réel. | Backend/API ou support serveur ; Frontend/navigateur ; Intégration/exploitation | NON EXÉCUTÉ — aucune preuve |
| AC28 | PR introduisant une alerte bloquante : fusion/déploiement empêchés. | Backend/API ou support serveur ; Intégration/exploitation | NON EXÉCUTÉ — aucune preuve |
| AC29 | Sauvegarde restaurée ailleurs : comptes de test, demandes et versions cohérents. | Backend/API ou support serveur ; Intégration/exploitation | NON EXÉCUTÉ — aucune preuve |
| AC30 | Retour au build précédent : service fonctionnel sans migration inverse destructrice. | Backend/API ou support serveur ; Intégration/exploitation | NON EXÉCUTÉ — aucune preuve |
| AC31 | Parcours mobile/clavier : aucune action essentielle inaccessible. | Frontend/navigateur ; Intégration/exploitation | NON EXÉCUTÉ — aucune preuve |
| AC32 | Recette complète A→B→C : résolution, publication, test et réutilisation reliés. | Backend/API ou support serveur ; Frontend/navigateur ; Intégration/exploitation | NON EXÉCUTÉ — aucune preuve |

## Enregistrement d’une preuve

Noter ID, commit réel, environnement, scénario/commande, date, attendu, observé, résultat et incident éventuel. B1-05 se teste avec une concurrence réellement mise en œuvre sur PostgreSQL. B2 requiert les vrais états réseau/stockage du navigateur. AC32 est validé définitivement seulement après le parcours intégré.

Les tests d’accessibilité supplémentaires suivent DESIGN_SYSTEM.md. Les objectifs de performance et restauration conservent leurs conditions de mesure du cahier. Une baisse de périmètre ou dérogation produit est une décision humaine écrite, pas une cellule passée artificiellement au vert.


## Extension de l’atelier — AC33–AC52

| ID | Attendu atelier | Portée | État / preuve |
|---|---|---|---|
| AC33 | L’intention unblock/review_solution/reproduce_behavior valide est conservée ; champ protégé rejeté ; anciennes demandes compatibles. | Backend + formulaire | NON EXÉCUTÉ — aucune preuve |
| AC34 | Un cas référence exactement une demande ou une version accessible ; double parent, absence et parent privé refusés. | Backend | NON EXÉCUTÉ — aucune preuve |
| AC35 | Réviser un cas soumis crée une révision ; la source intégrée reste figée et sa preuve conserve son digest. | Backend + interface | NON EXÉCUTÉ — aucune preuve |
| AC36 | Un cas humain revu ne devient jamais un programme exécutable ; champs code/command/URL de lancement rejetés. | Backend + worker | NON EXÉCUTÉ — aucune preuve |
| AC37 | Revue par personne distincte ; refus/retrait motivés ; intégration liée à un manifeste de release réel. | Backend + administration | NON EXÉCUTÉ — aucune preuve |
| AC38 | Seuls les profils B1 approuvés et compatibles peuvent être comparés ; paire arbitraire ou version retirée refusée. | Backend | NON EXÉCUTÉ — aucune preuve |
| AC39 | Les deux runs partagent entrées/seed/oracle/suite/environnement comparables et des fixtures distinctes. | Intégration PostgreSQL | NON EXÉCUTÉ — aucune preuve |
| AC40 | Deux runs réels produisent les observations ; le module naïf échoue le cas doublon, le corrigé le satisfait. | Worker + API | NON EXÉCUTÉ — aucune preuve |
| AC41 | Une comparaison réserve deux unités ; même clé ne recharge pas ; concurrence globale/membre partagée avec les runs simples. | Concurrence PostgreSQL | NON EXÉCUTÉ — aucune preuve |
| AC42 | Même clé/payload rejoue la même opération autorisée ; payload différent retourne 409 ; nouveau clic volontaire a un nouvel identifiant. | Backend + frontend | NON EXÉCUTÉ — aucune preuve |
| AC43 | Crash entre enfants, lease expirée et délai dépassé donnent un état explicite sans faux résultat complet ni double finalisation. | Worker/exploitation | NON EXÉCUTÉ — aucune preuve |
| AC44 | Les cinq conclusions improved/unchanged/regressed/mixed/inconclusive suivent les mêmes assertions ; régression jamais maquillée. | Unitaires + API | NON EXÉCUTÉ — aucune preuve |
| AC45 | Retrait ou secret expurge les vues du cas, de la comparaison et de la fiche, y compris accès direct et cache. | Backend + navigateur | NON EXÉCUTÉ — aucune preuve |
| AC46 | Diagnostic, cas, correction et revue sont attribués à leurs vrais acteurs ; données éditoriales/démo ne deviennent pas adoption externe. | Backend + interface | NON EXÉCUTÉ — aucune preuve |
| AC47 | P1 seulement : aperçu du contexte avant copie ; aucun envoi externe, contenu privé non autorisé absent, échec du presse-papiers explicite. | Frontend ; non applicable si P1 absent | NON EXÉCUTÉ — aucune preuve |
| AC48 | Comparer et lire le rapport à 360 px et au clavier fonctionne ; états nommés, contraste et focus contrôlés sans couleur seule. | Navigateur + revue manuelle | NON EXÉCUTÉ — aucune preuve |
| AC49 | Un ancien rapport reste daté ; relancer produit une nouvelle opération ; polling arrêté au terminal et hors écran. | Frontend + API | NON EXÉCUTÉ — aucune preuve |
| AC50 | Le parcours cas → revue → intégration approuvée → comparaison → fiche relie des IDs réels ; une préparation éditoriale est signalée. | API puis navigateur | NON EXÉCUTÉ — aucune preuve |
| AC51 | Un lien de reproduction dans un cas n’est pas récupéré automatiquement ; aucune instruction de contenu ne modifie l’agent ni le runner. | Sécurité | NON EXÉCUTÉ — aucune preuve |
| AC52 | Acceptations simultanées, deux comparaisons et un run simple ne dépassent ni contraintes de données ni limites atomiques. | Concurrence PostgreSQL | NON EXÉCUTÉ — aucune preuve |


## Rattachement au déploiement
AC19 et les tests de concurrence du comparateur appliquent désormais un seul run actif global ; quotas d’unités inchangés. Les tests navigateur s’exécutent aussi avec deux origines de confiance. Quatorze contrôles de déploiement séparés figurent dans DEPLOYMENT_GATE. Aucun AC exécuté par la création de ce dossier.


## Alignement communautaire — AC53 à AC68

Ces cas sont à exécuter. [M] fonde les usages ; les comportements et seuils sont des choix HAAS [H].

| ID | Priorité | Exigence | Situation et résultat attendu |
|---|---|---|---|
| AC53 | P0 | F16 | Compte actif vérifié crée un brouillon : owner_id serveur ; publication refusée si champs requis manquants ; aucun brouillon dans le catalogue public. |
| AC54 | P0 | F16 | Autre membre modifie/publie/archive le projet, ou fournit owner_id/publication_state dans PATCH : refus et aucune mutation ; modérateur ne réécrit pas le propriétaire. |
| AC55 | P0 | F16 | Publication valide puis recherche filtrée et pagination : données complètes, ordre stable, phase affichée comme déclaration, aucun projet privé/masqué exposé. |
| AC56 | P0 | F16 | Modification avec lock_version périmé : 409 et aucune écriture perdue ; archivage ferme les nouvelles demandes, garde l’historique autorisé et retire le projet du catalogue par défaut. |
| AC57 | P0 | F16 | Idempotency-Key rejouée : un seul projet ; même clé avec contenu différent : 409 ; liens dangereux/identifiants URL rejetés, aucune requête serveur vers les liens saisis. |
| AC58 | P0 | F16/F02 | Le propriétaire rattache sa demande à son projet publié ; un tiers, un projet privé/archivé/masqué ou un changement après publication sont refusés ; project_id absent reste valable. |
| AC59 | P0 | F16/F09 | Un projet masqué disparaît du catalogue et de l’accès direct public ; demandes liées, cas, compteurs, notifications et caches suivent ; sources de capsules expurgées/réexaminées si nécessaire. |
| AC60 | P0 | F17 | Annuaire vide par défaut pour un compte sans opt-in ; seul un compte actif vérifié volontaire apparaît ; courriel/IP/sessions/last_login ne figurent jamais dans la réponse. |
| AC61 | P0 | F17 | Recherche technologie/disponibilité et pagination retournent uniquement les membres autorisés dans un ordre stable, sans classement d’expertise ni score fabriqué. |
| AC62 | P0 | F17/F09 | Désactivation directory_visible ou suspension : retrait des résultats et cache annuaire ; anciennes contributions publiques gardent seulement l’attribution autorisée, pas les données privées. |
| AC63 | P0 | F17 | Disponibilité modifiée par le propriétaire : libellé auto-déclaré et horodatage ; aucun statut « en ligne » inventé ; un tiers ne peut modifier les préférences. |
| AC64 | P0 | F02 | ask_question sans code, expected ou attempts : enregistrement accepté si titre/contexte/question/technologie valides ; 422 sur les vrais champs manquants ; règles unblock inchangées. |
| AC65 | P0 | F16/F17 | Parcours mobile 360/390 px et clavier : projet consultable, membres filtrables, formulaire publiable ; focus et erreurs lisibles ; aucun défilement horizontal global. |
| AC66 | P0 | F16/F17 | Liste réellement vide distincte d’une panne réseau ; 401/403/409/422/429 correctement rendus ; déconnexion/changement de compte ne révèle pas les brouillons ni les préférences. |
| AC67 | P0 | F01–F18 | A publie projet et demande ; B trouve le projet/membre et contribue ; A accepte si pertinent ; C lit la capsule publiée et les contributions. Origine éditoriale/démo explicite ; branche B1 optionnelle dans ce parcours. |
| AC68 | P0 | F02/F03/F17 | Un membre pose une question de connaissance ; un autre commente utilement. Aucun dépôt, correctif, test, abonnement payant, messagerie privée ou résultat laboratoire n’est requis pour échanger. |

## Rencontre par le coup de main — AC69 à AC90

Prescriptions à exécuter sur le produit, pas résultats acquis. AC01–AC68 conservés, AC47 seul conditionnel.

| ID | Priorité | Exigence | Situation et résultat attendu |
|---|---|---|---|
| AC69 | P0 | F18 | **Ouverture volontaire.** Projet existant ou nouveau : help_open=false. Seul propriétaire actif vérifié ouvre un projet publié avec besoin/categories valides ; création ou publication ne coche pas l’opt-in. |
| AC70 | P0 | F18 | **Réglages et droits.** Autre membre/admin non propriétaire ne change pas help_open/categories ; catégorie vide, projet brouillon/masqué/archivé ou lock_version ancien refusés sans perte. |
| AC71 | P0 | F18 | **Découverte explicable.** Catalogue renvoie demandes publiques et projets ouverts, filtres stables et raisons exactes ; aucun pending privé, score expert, dernière connexion ou obligation annuaire. |
| AC72 | P0 | F18 | **Aucune occasion ou réseau.** Aucun résultat propose enlever filtres/explorer/question ; un incident HTTP n’affiche pas « aucun besoin ». Les demandes ordinaires restent accessibles. |
| AC73 | P0 | F18 | **Consentement du proposant.** Création active vérifiée avec résumé/résultat et accord explicite : pending privé ; refus 422 si accord absent. Aucun texte public, email ou secret recopié automatiquement. |
| AC74 | P0 | F18 | **Acteurs et champs protégés.** Auto-offre, rôle imposé, owner_id/proposer_id/state/accepted_request_id forgés ou compte suspendu/non vérifié : refus et zéro effet. |
| AC75 | P0 | F18 | **Création sans doublon.** Même Idempotency-Key/charge : même offre ; contenu différent :409. Deux clés concurrentes pour même couple : une seule pending et erreur contrôlée. |
| AC76 | P0 | F18 | **Quotas atomiques.** Requêtes simultanées sur plusieurs projets ne dépassent pas 5 créations/24h ni 5 pending ; échec ne laisse pas de réservation fantôme ni notification. |
| AC77 | P0 | F18 | **Confidentialité des offres.** Seuls deux acteurs lisent leurs offres ; autres utilisateurs et admin non habilité reçoivent refus sans fuite. Modération seulement sur dossier signalé audité. |
| AC78 | P0 | F18 | **Acceptation vers nouveau fil.** Deux consentements présents, champs normaux validés : 1 demande du propriétaire, 1 lien, 1 projection consentie. Un échec de validation/transaction laisse pending et aucun fil. |
| AC79 | P0 | F18 | **Acceptation vers fil existant.** Choix explicite même propriétaire/projet/état public ouvert : lien créé sans modifier texte. Fil autre projet/auteur, clos/masqué ou choix implicite rejeté. |
| AC80 | P0 | F18 | **Acceptation idempotente concurrente.** Double clic, nouvelle tentative même clé et deux acceptations concurrentes : une seule décision, pas deux discussions, projections ou notifications ; vieux lock_version=409. |
| AC81 | P0 | F18 | **Course acceptation/retrait.** Accept/withdraw, accept/decline et accept/fermeture réellement concurrentes aboutissent à un seul état cohérent ; aucun fil d’une offre retirée/fermée. |
| AC82 | P0 | F18 | **Expiration et fermeture.** Après 7 jours, acceptation refusée même cron arrêté. Fermeture/catégorie retirée/archivage expire pending ; accepted historique conservé ; nouvelles offres impossibles. |
| AC83 | P0 | F18 | **Suspension et masquage.** Suspension propriétaire/proposant bloque les actes, masque ce qui doit l’être ; projets/fils/offres/progrès/caches/liens directs respectent le parent après retrait. |
| AC84 | P0 | F18 | **Notifications et projection.** After-commit, une notification interne minimale par événement ; échec transaction aucune ; résumé public exactement consenti, source_offer_id unique et acteur système distinct. |
| AC85 | P0 | F18 | **Refus et retrait sans sanction.** Decline propriétaire/withdraw proposant pending seulement ; motif privé, pas score ni honte publique ; offre expirée ne devient pas refus du propriétaire. |
| AC86 | P0 | F18 | **Progrès sans faux résultat.** Accepted affiche collaboration commencée, pas amélioration réalisée ; seul résultat/commentaire/capsule effectivement enregistré apparaît avec nature réelle et visibilité ; pas faux compteur. |
| AC87 | P0 | F18 | **Autonomie de la communauté.** Membre hors annuaire peut aider ; question sans code, contribution à demande ouverte et fermeture des offres coexistent ; aucun laboratoire ou aide préalable imposé. |
| AC88 | P0 | F18 | **Résilience et cache frontend.** 401/403/404/409/419/422/429/réseau/annulation : état juste, pas acceptation optimiste ; saisie en mémoire ; rejeu même intention ; logout/change compte purge offres privées. |
| AC89 | P0 | F18 | **Consentements accessibles.** À 360/390px, clavier et zoom200% : deux consentements non précochés lisibles ; erreur proche du champ, focus/restauration dialog corrects, pas piège clavier ni débordement global. |
| AC90 | P0 | F18 | **Première collaboration complète.** A ouvre projet sans demande ; B découvre/propose ; A accepte après aperçu et crée un fil ; B apporte une observation ; résultat éventuel attribué sans lab obligatoire. Vérifier aussi rattachement/refus/retrait et aucune permission supplémentaire. |
