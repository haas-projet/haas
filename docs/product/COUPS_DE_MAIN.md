# HAAS — La rencontre par le coup de main

**Exigence : F18.** Décision de conception retenue par la demande de mise à jour du ZIP, le 1er octobre 2026. **Source :** proposition « La rencontre par le coup de main » puis accord de l’utilisateur. Le mail CADEV décrit les usages ; il n’impose ni cette fonction ni ses détails techniques. **Statut :** à développer et à vérifier ; aucune application ni collaboration réelle n’est attestée dans ce pack.

> Rencontrez-vous en construisant quelque chose ensemble.

## 1. Résultat visé et périmètre

Un propriétaire ouvre son projet à un petit coup de main. Une autre personne propose un apport concret. Le propriétaire accepte et un échange public contextualisé commence dans les fils existants. Une réponse, un test, une relecture ou une amélioration de documentation peut ensuite contribuer au projet. La capsule et le laboratoire restent facultatifs pour les personnes ; les démonstrateurs prévus restent à livrer.

Deux entrées d’accueil : **« Faire avancer mon projet »** et **« Donner un coup de main »**. Explorer les projets, lire les solutions ou poser une question reste possible sans choisir une entrée. Aucune contribution préalable n’est exigée pour demander de l’aide.

F18 ajoute une offre bornée avec consentement, pas une messagerie générale, un contrat de prestation, une équipe ou un accès au dépôt. Aucun paiement, calendrier, rendez-vous, délai de réponse garanti, chat privé, terminal, classement ou moteur IA. Un seul échange d’offre avant acceptation ; pas de réponses privées imbriquées.

**Évolution explicite de F16 :** en l’absence de demande ouverte, un membre peut maintenant proposer son aide si le propriétaire a activé l’ouverture aux coups de main. Le tiers ne peut toujours pas créer librement une demande au nom du propriétaire ni la rattacher à son projet. C’est l’acceptation du propriétaire qui autorise le lien, dans une transaction. Les discussions existantes restent utilisables quand les offres spontanées sont fermées.

## 2. Ouverture volontaire et types de contributions

Ajouter aux projets `help_open`, faux par défaut, et `help_categories`. Pour ouvrir : propriétaire actif et vérifié, projet publié et non masqué/archivé, `help_sought` décrivant le petit résultat attendu (30–1 500 caractères), 1 à 5 catégories. Aucun projet existant ne devient ouvert lors de la migration. Fermer ne supprime ni projet ni discussion.

| Valeur stable | Libellé UI | Exemple de contribution |
|---|---|---|
| usability_feedback | Retour sur l’interface | Signaler où le parcours devient difficile. |
| code_review | Relecture technique | Examiner un extrait volontairement partagé. |
| reproduce_behavior | Reproduction d’un comportement | Décrire les étapes et l’observation obtenue. |
| explain_concept | Explication | Clarifier un concept avec son contexte. |
| documentation | Documentation | Essayer ou améliorer une procédure. |

Ces catégories décrivent une intention, pas une expertise certifiée. Dans le profil, `preferred_help_categories` est facultatif ; les technologies existent déjà. La visibilité annuaire (`directory_visible`) reste un consentement distinct : aider n’exige pas l’opt-in à l’annuaire. Un compte suspendu ne peut ni ouvrir, proposer, accepter, retirer ou décliner ; il garde seulement les accès de recours existants.

## 3. Découvrir une occasion concrète d’aider

`GET /api/v1/help-opportunities` agrège deux types explicites :

- `request` : demande publique ouverte/en cours, auteur actif, visibilité parent respectée. L’action mène au fil existant pour y contribuer directement, sans offre obligatoire.
- `project` : projet publié, non masqué/archivé, propriétaire actif, `help_open=true`. L’action propose un coup de main. Si une demande publique pertinente existe, la carte propose d’abord de la consulter ; un projet ne doit pas remplir le flux avec des doublons de cartes quasi identiques.

Filtres en liste autorisée : technologie, catégorie, type ; pagination 20/max50, tri `published_at DESC, id ASC` stable, pas de score social. Les demandes ne portent pas toutes une catégorie : ne pas en inférer une ; le filtre catégorie ne les retient que si une catégorie a été déclarée dans le besoin. Pour limiter la migration, utiliser la catégorie du projet pour les demandes liées et présenter cette origine explicitement. Les demandes autonomes sans catégorie restent dans la vue non filtrée.

Les préférences ne servent qu’à préremplir les filtres après accord et à expliquer les correspondances exactes. Le visiteur peut modifier/retirer chaque filtre. Afficher par exemple « React figure parmi vos technologies sélectionnées ». Ne pas écrire « Le meilleur développeur pour vous ». Les raisons sont dérivées de champs publics, jamais du dernier login ou d’une inférence d’expertise.

Une liste vide signifie seulement qu’aucun besoin visible ne correspond. Actions : retirer un filtre, explorer les projets, poser une question. Une panne réseau ne devient pas un état vide. Le catalogue public ne révèle aucun nombre d’offres en attente ou refusées.

## 4. Proposition limitée et consentement à la publication

Un membre actif vérifié, autre que le propriétaire, remplit une fiche courte. Avant acceptation, seul le proposant, le propriétaire et la modération habilitée sur un signalement peuvent lire l’offre. Il n’existe pas de galerie publique d’offres ni de refus publics.

| Champ proposé | Contrat |
|---|---|
| category | Une catégorie actuellement ouverte sur le projet. |
| public_summary | 30–800 caractères ; apport concret, pas coordonnées personnelles ni secret. |
| expected_outcome | 20–500 caractères ; résultat limité : observation, explication ou procédure. |
| allow_public_summary | Booléen explicitement true : accord pour afficher ces deux textes dans un échange public si l’auteur accepte. Jamais précoché. |

L’aperçu montre exactement les deux textes qui pourront devenir publics, le projet et le pseudonyme attribué. Le formulaire prévient : « Cette proposition est visible par vous et le propriétaire. Si elle est acceptée, ce résumé et le résultat proposé rejoindront un échange public. » Le contact e-mail n’est jamais ajouté. Pas de texte caché importé depuis une conversation privée, pas de fetch de lien, d’image, de fichier ou d’exécutable.

Les textes d’une offre soumise sont figés pour cette décision. Pour les modifier, retirer l’offre encore en attente puis en créer une nouvelle, dans les limites d’usage. Le propriétaire ne modifie pas silencieusement les mots du proposant.

Le refus ne donne pas de malus ; le proposant peut retirer une offre en attente. Un motif de refus est facultatif, court, privé aux deux acteurs, modérable et non recopié dans les notifications. Pas de rappel automatique agressif.

## 5. États et transitions

`HelpOfferState = pending | accepted | declined | withdrawn | expired`.

| État de départ | Action / acteur | État d’arrivée | Effet |
|---|---|---|---|
| absent | proposer / membre autorisé | pending | Offre privée, expiration à 7 jours UTC, notification minimale au propriétaire. |
| pending | accepter / propriétaire | accepted | Crée ou rejoint UN fil autorisé, publie le résumé consenti, inscrit un événement attribué. |
| pending | décliner / propriétaire | declined | Aucun fil, aucune pénalité publique. |
| pending | retirer / proposant | withdrawn | Aucun fil ; possibilité de proposer de nouveau dans les limites. |
| pending | échéance atteinte / système | expired | Aucun fil ni acceptation tardive ; motif interne `time_limit`. |
| pending | fermeture, archivage, masquage ou suspension / système | expired | Motif interne adapté ; pas de refus attribué artificiellement au propriétaire. |
| accepted | consulter le fil | accepted | L’accord est une trace historique, pas un travail achevé. |

Les états terminaux sont immuables ; pas de retour automatique à pending. Une offre acceptée ne porte pas de statut « terminé », de score ou de preuve technique. Le travail se poursuit dans le fil. Si quelqu’un s’arrête, il peut le signaler dans le fil ; l’archivage/réouverture existants restent les mécanismes de suivi. Le retrait d’un contenu dangereux peut expurger la projection publique via la modération, sans falsifier l’historique.

L’expiration est évaluée sur le serveur à chaque lecture ou commande critique, pas uniquement par cron. La purge physique et la rétention suivent une décision documentée de confidentialité ; « expiré » ne signifie pas supprimé.

## 6. Accepter sans fabriquer un projet, un fil ou un résultat

La page d’acceptation montre l’offre figée et l’espace public de destination. Le propriétaire choisit explicitement :

**Créer un échange.** Le titre, le contexte, la question/le point à examiner et l’intention sont préremplis pour économiser la saisie, mais restent à relire. La publication valide tous les champs nécessaires au même `CreateHelpRequestService` que les demandes ordinaires. Pour `ask_question`, aucun code, expected ou attempts n’est exigé. `publish_consent=true` est un accord distinct du propriétaire, non précoché. Le serveur fixe `author_id` au propriétaire actuel ; aucune usurpation du proposant.

**Rejoindre un échange.** Le propriétaire sélectionne une de ses demandes publiques, ouvertes/en cours, rattachées à CE projet. L’interface affiche le fil exact ; pas de sélection silencieuse « dernier fil ». La commande vérifie de nouveau tous les droits/états. Aucun titre, texte ou contenu déjà présent n’est remplacé.

Dans les deux cas : transaction unique pour décision, création éventuelle du fil, lien unique et projection publique du résumé consenti. Un événement système `help_offer_accepted` porte l’acteur décideur et le proposant distinctement ; il n’est pas présenté comme un commentaire rédigé par ce dernier. Aucune « contribution terminée » n’est ajoutée. Les permissions du proposant restent celles d’un membre ordinaire.

Une seule offre active en attente par couple projet/proposant. Pour éviter la multiplication accidentelle de fils, l’acceptation n’a jamais un mode implicite. Elle crée une nouvelle demande uniquement après confirmation de ce choix ; sinon elle rattache le fil sélectionné. Plusieurs offres acceptées peuvent rejoindre le même fil si le propriétaire le choisit explicitement.

## 7. Transactions, doublons et limites d’usage

Toutes les commandes sensibles utilisent actor serveur, DTO validé, Policy, transaction, audit et Resource whitelist. `lock_version` obligatoire sur les transitions ; `Idempotency-Key` obligatoire sur création et acceptation. Le rejeu de la même clé/empreinte rend le même identifiant sans nouvelle discussion/notification. Même clé et corps différent : 409. Les droits et la visibilité courants sont revérifiés avant lecture d’une réponse mémorisée ; ne pas rendre un ancien contenu devenu masqué.

Ordre de verrouillage à documenter et partager pour les commandes qui touchent plusieurs objets : lignes de quotas/acteurs nécessaires dans un ordre déterministe, projet, offre, puis demande liée. Ne jamais acquérir ces verrous en ordre inverse. Les services appelés ne doivent ni ouvrir un second commit ni envoyer de mail dans la transaction. Revalider projet ouvert, catégorie, acteurs, délai, état et lien. Les autres commandes qui peuvent invalider ces invariants participent au protocole de verrouillage et aux tests de concurrence (fermeture, suspension, archivage, acceptation et retrait).

Contraintes minimales : index unique partiel `help_offers(project_id, proposer_id) WHERE state='pending'` ; unicité de la projection publique `source_offer_id` ; FK de `accepted_request_id` avec contrôle du même projet/auteur ; `accepted` implique request_id/accepted_at non nuls ; états et dates cohérents. Pas de cascade qui efface les discussions. Expirer les offres périmées avant de libérer/créer une nouvelle offre ; un index unique ne gère pas le temps automatiquement.

Limites proposées et configurables : **5 créations par 24 heures glissantes et par membre**, **5 offres en attente globalement par membre**, **1 offre en attente par projet/membre**. Deux créateurs concurrents ne contournent pas ces limites : réservation atomique côté serveur, pas un COUNT puis INSERT non verrouillé. Ces seuils sont des choix à ajuster après essai, pas des garanties de réponse ni un quota de laboratoire. Notification interne une fois après commit par événement, avec rattrapage/clé d’unicité prévus selon les mécanismes existants.

## 8. Confidentialité, retraits et modération

Avant acceptation, l’offre ne figure ni dans recherche publique, ni profil, ni compteur, ni payload d’un projet. Après acceptation, seule sa projection consentie est visible dans le fil si tous les parents sont visibles. Les états declined/withdrawn/expired, motifs privés et identifiants d’acteurs non publics ne sont jamais divulgués par la fiche de progrès.

Le masquage/archivage ferme les nouvelles offres et empêche leur acceptation ; le masquage rend les fils liés inaccessibles au public selon F16. Une fermeture de `help_open` seule n’efface pas les fils déjà ouverts. La suspension d’un proposant n’invente pas un échec de son travail ; elle bloque ses actions et les décisions en attente. Le traitement respecte la différence entre l’annuaire opt-in et l’attribution publique existante.

La modération peut consulter une offre signalée dans le dossier concerné, masquer sa projection ou traiter le signalement avec audit. Aucun administrateur non propriétaire ne l’accepte au nom de l’auteur. Aucune donnée brute d’offre dans les logs ou notifications ; lien authentifié et message minimal. À la déconnexion : annuler lectures et vider les caches privés, y compris les offres reçues/envoyées. Ne pas mettre ces réponses dans un cache CDN public.

## 9. Voir ce que nous avons amélioré ensemble

La fiche projet affiche une vue des échanges et résultats autorisés : contribution liée, résolution validée par l’auteur, capsule publiée et, lorsque disponible, vérification de la bonne version. L’offre acceptée peut apparaître comme **« Collaboration commencée »**, jamais comme **« Amélioration réalisée »** avant un résultat enregistré.

Cette vue ne crée ni nouveau score, ni certificat, ni pourcentage de projet terminé. Elle n’affiche pas les offres en attente. Une explication utile sans résolution peut rester visible comme échange, sans être promue automatiquement en correction vérifiée. Les données de démo sont identifiées et exclues des chiffres d’adoption.

## 10. API, écrans, livraison

Lire `docs/api/COUPS_DE_MAIN_API.md`, les écrans UX21–23 et les tests AC69–90. Les classes et routes sont des spécifications à implémenter dans l’architecture existante ; pas un serveur livré. Les technologies, catégories et états sont typés ; aucune requête HTTP dans JSX, pas de service global contenant tous les cas d’usage.

Les offres ne lancent jamais un laboratoire. Un cas documentaire doit toujours suivre la revue et la release des scénarios approuvés. Systalink + Vercel, PostgreSQL local, runner restreint, Qodana Ultimate et les portes humaines restent inchangés. Les seize lots BH01–BH10/FH01–FH05/RH01 complètent le plan ; chaque lot est découpable en petits commits testables. Réestimer le travail à partir du dépôt. Retirer les P1 avant d’alléger les droits, consentements ou tests de concurrence.

## 11. Pilote et démonstration honnêtes

Scénario fictif : Awa présente une application et ouvre un retour d’interface ; Moussa propose de tester ; Awa accepte ; un fil public consenti est créé ; l’observation puis la correction sont documentées. Une comparaison B1 peut ensuite illustrer un cas approuvé. Le résumé d’offre, la contribution réalisée et le rapport machine restent distingués.

Cible qualitative proposée : observer trois petits coups de main entre testeurs volontaires, en consignant aussi refus, abandons et non-réponses. Mesurer découverte→offre, offre→acceptation et acceptation→premier apport utile. Ne pas interpréter une acceptation comme un travail accompli. Un testeur doit pouvoir expliquer ce qui deviendra public avant de confirmer. Les résultats, durées et noms ne sont renseignés que s’ils ont réellement été observés et autorisés à la présentation.
