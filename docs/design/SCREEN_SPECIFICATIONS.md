# HAAS — Spécifications des écrans

Les routes d’interface sont des propositions, pas des modifications des routes API. États, capacités et version sont issus du serveur. Cette spécification est préparée avant l’interface ; elle n’autorise pas à commencer React avant le gate.

## UX01 — Accueil communautaire

**Route :** /

**But :** Comprendre la communauté et voir comment y participer avant l’inscription.

**Composition :** Logo HAAS ; promesse « Présentez vos projets. Trouvez de l’aide. Construisez ensemble. » ; action « Explorer les projets », action secondaire « Demander de l’aide », lien Développeurs. Trois aperçus publics : projets, questions en cours, solutions partagées. L’atelier est présenté ensuite par un exemple, pas imposé comme écran d’entrée.

**Interactions :** Projet → fiche → échange ouvert ; développeur → profil → travaux publics. « Présenter mon projet » mène à UX19 après connexion. Aucun carrousel, KPI de communauté, faux membre en ligne ou compteur décoratif.

**États et accessibilité :** Contenus éditoriaux marqués ; vide différent d’une indisponibilité ; lecture clavier/mobile, titres explicites et deux boutons maximum dans le bloc principal. Consulter ne nécessite pas de compte ; contribuer oui.

## UX02 — Explorer

**Route :** /explorer

**But :** Trouver une demande ou une solution avec le moins de manipulations possible.

**Composition :** Champ de recherche visible ; type demande/capsule ; filtre technologie, état ou laboratoire lorsqu’applicable ; compteur réellement issu du résultat ; cartes à titre cliquable, technologies et statut de preuve ; pagination.

**Interactions :** Filtres encodés dans URL, retour navigateur préservé ; remise à zéro visible ; soumission clavier ; version liée à chaque capsule. Ne pas transformer une consultation de catalogue en formulaire à dix filtres.

**États et accessibilité :** Chargement annoncé sans effacer inutilement les résultats précédents ; aucun résultat = expliquer les filtres et proposer de les supprimer ; réseau = reprendre ; cartes une colonne. Les résultats ne révèlent jamais les brouillons et contenus retirés.

## UX03 — Connexion et compte

**Route :** /connexion ; /inscription ; /mot-de-passe-oublie

**But :** Créer un compte, entrer et récupérer l’accès sans ambiguïté.

**Composition :** Un formulaire centré ; labels persistants ; afficher/masquer mot de passe par bouton nommé ; liens réciproques connexion/inscription ; conditions versionnées ; écran de vérification du courriel.

**Interactions :** Autofill adapté et collage autorisé. Différencier erreur de saisie, courriel non vérifié, compte suspendu et problème réseau. Liens de retour internes contrôlés pour éviter les redirections ouvertes.

**États et accessibilité :** Pas d’écran blanc pendant /me. Réponse de mot de passe oublié non révélatrice de l’existence d’un compte. Le bouton de renvoi explique le délai. Aucune persistance automatique de mot de passe ni token.

## UX04 — Mon espace

**Route :** /espace

**But :** Reprendre un travail existant plutôt que regarder des chiffres décoratifs.

**Composition :** Blocs utiles : mes projets, mes demandes actives, brouillons, favoris récents. Liens vers contributions et notifications. Priorité aux tâches réelles : demande en attente, capsule à corriger, retour de laboratoire disponible.

**Interactions :** Première connexion : une explication courte et bouton « Créer ma première demande ». Retour utilisateur : liens directs vers le bon objet/version. Pas de KPI commerciaux ou de classement de compétence.

**États et accessibilité :** Squelette cohérent ; compte vide expliqué ; indisponibilité séparée du vide ; session expirée gérée sans exposer le cache privé. Informations non nécessaires repliées.

## UX05 — Demande guidée

**Route :** /demandes/nouvelle ; /demandes/:id/modifier

**But :** Poser une question ou exposer un blocage en étapes relisibles, sans code obligatoire.

**Mode question :** help_intent=ask_question affiche titre, contexte, question et technologies ; essais/résultat attendu/extrait facultatifs. Les intentions techniques gardent leurs règles. project_id facultatif seulement pour un projet publié du propriétaire ; retour à la fiche après création. Une question peut recevoir des commentaires sans passer par un laboratoire.

**Composition :** Étape 1 Contexte : titre, objectif, technologies/version. Étape 2 Problème : attendu, observé, essais, extrait facultatif et environnement. Étape 3 Vérifier : résumé, visibilité publique et avertissement secrets. Enregistrer brouillon et publier sont distincts.

**Interactions :** Étapes précédentes modifiables sans perdre la saisie ; code facultatif ; validations et limites du cahier ; avertissement si motifs de secrets. Draft enregistré seulement après réponse. Avant de quitter avec modifications non enregistrées, informer.

**États et accessibilité :** 422 lié aux champs, focus approprié ; 409 propose de recharger sans écraser le travail en mémoire ; échec réseau distingue envoi incertain. Aucun défilement horizontal global. Ne pas ajouter cinq étapes supplémentaires.

## UX06 — Discussion et propositions

**Route :** /demandes/:id

**But :** Comprendre le contexte et apporter une aide précise.

**Composition :** Titre, auteur, état, technologies ; contexte ; discussion ; propositions structurées ; panneau de synthèse sur grand écran. Dans les propositions : diagnostic, correction, vérification, limites. Actions commenter/proposer distinctes.

**Interactions :** Extraits inertes copiables. Horodatage et historique de modification visibles. L’auteur retrouve la proposition à accepter sans menu caché. Rafraîchissement léger ne vole pas le focus ni ne déplace la lecture.

**États et accessibilité :** Sans contribution : invitation à proposer, pas de faux message. Demande archivée = lecture autorisée selon règles et actions fermées. Contenu masqué = explication neutre. Sur mobile synthèse suit le contexte.

## UX07 — Accepter ou rouvrir

**Route :** Dialogue depuis la demande

**But :** Faire une décision attribuée, comprise et réversible selon les règles.

**Composition :** Dialogue « Accepter cette proposition » avec auteur/titre de proposition, environnement et note de validation. Texte : cela confirme une aide dans votre contexte, pas une certification. Réouverture distincte avec motif.

**Interactions :** Actions visibles selon capacités du serveur, mais API vérifie toujours. Confirmer ne disparaît pas tant que l’envoi est pending ; double clic prévenu. Réouverture signale l’effet sur les capsules liées.

**États et accessibilité :** 403 = aucune acceptation inventée ; 409 = demande déjà changée ; réseau = vérifier résultat côté serveur. Focus initial sur titre/champ pertinent, focus restauré sur le déclencheur.

## UX08 — Édition et revue de capsule

**Route :** /capsules/nouvelle ; /capsules/:id/versions/:version/edition

**But :** Transformer une résolution en fiche compréhensible et soumettre à une autre personne.

**Composition :** Sections problème, cause, correction, procédure, limites, versions, contributeurs et provenance. Lien source visible. Prévisualisation et checklist avant soumission. Revue séparée du brouillon.

**Interactions :** Enregistrer → Soumettre à la revue → Corriger si demandé. L’auteur ne voit pas « Publier » comme raccourci d’auto-validation. Nouvelle version pour corriger une publication ; ne pas modifier le passé.

**États et accessibilité :** Source rouverte = avertissement et réexamen ; licence non confirmée = diffusion bloquée. Erreurs proches des sections. Format long avec sommaire discret, jamais un unique textarea immense.

## UX09 — Lire une capsule

**Route :** /capsules/:slug

**But :** Évaluer une solution et savoir exactement ce qui est vérifié.

**Composition :** Titre/version/technologies ; résumé ; procédure ; code et limites ; bloc preuve datée ; contributeurs ; retours. Le choix de version reste visible et l’URL permet de partager la version choisie.

**Interactions :** Sans lab : bouton « Voir la procédure », jamais « Rejouer » inactif sans explication. Avec lab : scénario autorisé et lancement. Télécharger dépend des droits réels. Favori et retour humain sont secondaires.

**États et accessibilité :** Version retirée : motif autorisé, téléchargement fermé, aucune fuite via ancienne URL ; sans kit : ne pas afficher de faux lien ; rapport ancien daté. Lecture mobile d’abord, preuves en cartes empilées.

## UX10 — Rapport de laboratoire

**Route :** /laboratoires/:id

**But :** Comprendre un résultat sans connaître le moteur technique.

**Composition :** Version, scénario, identifiant/date, état, durée ; liste attendu/observé par assertion ; digests dans une section technique repliable. Statuts distincts : en file, en cours, réussie, échouée, erreur, délai dépassé.

**Interactions :** Lancer crée une intention nouvelle ; répéter une reprise technique conserve la clé. Polling borné et suspendu hors activité. Réussi seulement après résultat du worker. Nouvelle exécution ne remplace pas silencieusement l’ancien rapport.

**États et accessibilité :** 429 = expliquer quota ; 503 = laboratoire indisponible sans bloquer lecture de capsule ; timeout = absence de conclusion. Pas de barre « 86 % » sans mesure réelle. Pas de succès créé dans le navigateur.

## UX11 — Profils, favoris et retours

**Route :** /membres/:handle ; /espace/favoris

**But :** Voir le travail et mémoriser une solution sans fabriquer une réputation.

**Composition :** Profil avec pseudonyme, biographie, technologies et contributions liées ; email jamais public. Favoris privés. Retour attaché à une version : reproduit, partiellement reproduit, non reproduit et contexte.

**Interactions :** Lien vers la contribution et rôle réel. Auto-résolution et auto-test identifiés, pas valorisés comme preuves indépendantes. Un retour peut être corrigé avec historique.

**États et accessibilité :** Profil sans contribution = message neutre ; liste favoris vide = lien explorer ; retrait d’un contenu ne conserve pas de faux badge. Aucun classement général ajouté.

## UX12 — Notifications

**Route :** Panneau de l’en-tête et page accessible

**But :** Retrouver une action utile et son contexte.

**Composition :** Liste datée, lu/non lu, titre précis et lien vers la ressource. Compteur issu de l’API, pas localement incrémenté sans événement confirmé.

**Interactions :** Contrôle clavier du panneau, ouverture d’une notification vers la bonne version. Ne pas lire tous les messages par aria-live à chaque polling. Aucune copie de secret ou de code dans le message.

**États et accessibilité :** Vide = « Aucune notification pour le moment ». Erreur = reprendre sans remettre arbitrairement tout à zéro. Notification d’un contenu retiré mène à l’état autorisé, pas à son ancienne copie.

## UX13 — Modération et administration

**Route :** /admin

**But :** Prendre des décisions traçables sans devenir une suite de gestion inutile.

**Composition :** Quatre files : signalements, capsules en revue, comptes, incidents labo. État/date/catégorie. Détail contextuel avec contenu accessible selon droits et action motivée.

**Interactions :** Publication par réviseur distinct ; masquer/retirer nécessite motif ; suspendre compte réservé admin. Voir les actions précédentes. Aucune validation de résolution au nom d’un tiers.

**États et accessibilité :** Accès interdit expliqué ; tâches vides positives mais sans faux indicateurs ; conflits concurrents actualisent la décision. Tables adaptatives et actions nommées, pas série de petites icônes sans texte.

## UX14 — Démonstrateur B2

**Route :** Origine de démonstration distincte

**But :** Montrer une saisie conservée puis confirmée après une vraie coupure.

**Composition :** Bandeau « Données fictives — démonstration ». Formulaire minimal, liste locale et états : conservée sur cet appareil, envoi en cours, confirmée par le serveur, échec à reprendre. Version du kit visible.

**Interactions :** Créer la clé avant premier envoi, stocker dans IndexedDB, reprendre manuellement après réseau. Recharger ou rouvrir retrouve la saisie si le stockage existe. Pas de cookies HAAS, aucun cache de profils/auth de HAAS.

**États et accessibilité :** Stockage bloqué ou effacé = avertissement clair ; navigator.onLine ne prouve pas un accusé serveur. Envoyer deux fois conserve une seule commande fictive. Captures et test navigateur datés, sans faux label de lab serveur.

## Protocole commun de revue

Avant livraison d’un écran, répondre : l’utilisateur comprend-il le sujet en lisant le titre ; repère-t-il une action principale ; sait-il ce qui a été enregistré ; peut-il revenir sans perdre le contexte ; peut-il utiliser le clavier ; les textes et controls restent-ils lisibles sur mobile ?

Conserver une preuve par état critique et un parcours navigateur réel. Les captures seules ne prouvent ni sécurité, ni bonne persistance. Relier la recette aux AC existants sans les renommer.

## UX15 — Proposer et suivre un cas

**Action principale :** « Proposer un cas à vérifier ». Formulaire en trois blocs courts : situation, étapes, attendu. Résultat observé facultatif et aide « Laissez vide si vous ne l’avez pas encore reproduit ». Parent/version explicitement visibles, pas de sélecteur ambigu latest.

Statuts textuels : Brouillon / Soumis / Examiné / Intégré à un scénario / Non retenu / Retiré. Examiné ≠ exécuté. Confirmation avant envoi public ; code inerte et alerte de secrets. Une révision fait apparaître date et historique. Le mobile conserve labels, étapes et erreurs sans accordéon obligatoire.

## UX16 — Comparer deux versions approuvées

**Action principale :** « Comparer les versions ». En-tête : capsule, scénario et deux versions. Référence étiquetée « Exemple pédagogique incorrect » ; candidat étiqueté « Version à vérifier », jamais « gagnante » avant exécution.

Avant lancement : description de ce qui sera exécuté, coût deux unités et limites. En cours : baseline/candidat avec progression de phases réelle, pas un pourcentage inventé. Résultat : même cas, attendu, observé A et B, conclusion textuelle. Les colonnes deviennent deux cartes empilées à 360 px. Aucun slider qui cacherait des données, aucune animation automatique obligatoire.

Panne d'un côté : conserver son statut, afficher « Comparaison incomplète ». Autres conclusions : amélioration sur ces cas / conformité inchangée / régression / résultats mitigés. Afficher la date exacte et le bouton « Relancer » qui crée une nouvelle intention. Détails digests/configuration dans une section secondaire accessible au clavier. Les captures illustratives sont identifiées comme telles.

## UX17 — Fiche de vérification et contributions

**Action principale :** consulter la preuve de la version choisie, puis contribuer un cas ou réutiliser selon disponibilité. Blocs : version, cas examinés, qui a fait quoi, résultats, limites. Types de preuve distincts et nommés, aucune note de fiabilité.

Un nouveau visiteur doit pouvoir différencier observation humaine, acceptation et test machine sans ouvrir une documentation. Les limites visibles ne sont pas cachées dans un tooltip. Résultat absent : message clair, pas un emplacement de badge vide. P1 copier pour son assistant : aperçu/annulation, contrôle de la copie réelle et zéro transmission externe.

## Revue de l’atelier
Ouvrir les écrans réels à 360/390/768/1280 px, au clavier, avec zoom et contenus longs. Tester toutes les conclusions et un réseau interrompu. Pas de couleur seule pour A/B ni pour la réussite. Conserver les mêmes couples de contraste et taille de texte que la charte.


## UX18 — Découvrir un projet et ses échanges

**Routes :** /projets ; /projets/:id.

**But :** Comprendre ce qui est construit et trouver un point de contribution concret.

**Composition :** Recherche, technologie et phase déclarée ; cartes de nom/résumé/stack/propriétaire et lien « Voir le projet ». Détail : description, besoin d’aide, liens HTTPS facultatifs, échanges publics et contributions. Les phases « Prototype »/« En ligne » sont déclaratives, sans badge de qualité.

**Interactions :** Propriétaire : « Demander de l’aide pour ce projet » (UX05 prérempli) et « Modifier ». Tiers : « Voir les échanges » puis commentaire/proposition sur une demande. Aucun fil dupliqué, bouton d’équipe ou iframe externe. Un lien extérieur est signalé et garde un nom accessible.

**États et accessibilité :** Chargement, vide, aucun résultat, erreur, privé/masqué et archivé traités séparément ; compteurs uniquement autorisés. À 360 px, cartes une colonne et boutons à texte ; code défile dans son conteneur. Projet sans échange : offre F18 si help_open, sinon état fermé et lien vers d’autres besoins ; aucun CTA sans destination.

## UX19 — Présenter et gérer son projet

**Routes :** /projets/nouveau ; /projets/:id/modifier ; onglet Mes projets dans /espace.

**But :** Publier une fiche claire, sans devoir importer un dépôt ou fournir une capture.

**Composition :** Nom, résumé, description, technologies, phase déclarée, besoin d’aide facultatif, liens facultatifs ; récapitulatif de visibilité. Un formulaire segmenté, pas un tunnel de dix étapes. Initiales remplaçant un upload d’image.

**Interactions :** « Enregistrer le brouillon » et « Publier le projet » distincts ; publier annonce visibilité publique et nécessite confirmation serveur. Archivage avec motif. Conflit 409 conserve les valeurs en mémoire et explique la reprise. Aucun transfert implicite des droits du dépôt externe.

**États et accessibilité :** 422 près des champs et résumé des erreurs, focus première erreur ; réseau/401/403/429 visibles ; pas de localStorage automatique de contenu privé. Succès uniquement après API et invalidation des listes ; brouillons privés par session.

## UX20 — Découvrir des développeurs

**Routes :** /developpeurs ; /membres/:handle ; préférences dans /espace/profil.

**But :** Retrouver des personnes par technologie et travaux visibles, sans remplacer l’échange public par une messagerie privée.

**Composition :** Recherche pseudonyme/texte, filtre technologie, disponibilité déclarée ; cartes de pseudonyme, bio, stack, intention d’aide et lien « Voir le profil ». Profil : projets publics et contributions. Préférence non cochée par défaut « Afficher mon profil dans l’annuaire » ; disponibilité séparée et datée.

**Interactions :** Visiter un projet ou une contribution pour participer au fil concerné. « Disponible pour aider » reste une déclaration ; pas de pastille en ligne, de délai promis, de score, de bouton privé ou d’invitation fictive.

**États et accessibilité :** Retrait annuaire immédiat après confirmation et purge cache ; aucun courriel/IP/last_login. Vide et panne distincts ; focus correct des filtres ; pagination nommée ; mobile et zoom sans perte d’action.

## UX21 — Trouver une première occasion d’aider

**Routes :** accueil et /coups-de-main. Deux actions « Faire avancer mon projet » / « Donner un coup de main », plus accès à Explorer et Poser une question sans tunnel. Carte : projet, besoin borné, stack, catégorie, raison factuelle du filtre, action nommée. Un fil public ouvert mène directement à sa discussion ; un projet ouvert aux offres permet de proposer une aide. Aucun indicateur en ligne ni délai garanti.

**États :** filtres modifiables dans l’URL, choix de catégories facultatif, six cartes maximum au premier écran puis pagination. Vide : enlever un filtre/explorer/poser une question. Erreur réseau distincte, liste sans faux contenu. Projets privés et offres pending absents. Corps 16px, contrôles confortables 44px visés, focus visible, sans colonne qui déborde à360px.

## UX22 — Proposer un coup de main en confiance

**Route :** /projets/:id/coup-de-main. Formulaire court : catégorie, ce que je propose, résultat recherché. Au-dessus : projet et propriétaire, visibilité du résumé. Aperçu exact de la projection future et case non précochée « J’accepte que ce résumé et le résultat proposé deviennent publics si le propriétaire accepte ». Aucun e-mail demandé ni secret stocké localement.

**Action :** « Envoyer ma proposition ». Après API : « Proposition envoyée au propriétaire. Vous pouvez la retirer tant qu’elle n’est pas acceptée. » Pas « collaboration réussie ». Les catégories/états sont revalidés côté serveur. 409 explique fermeture ou autre pending ; 422 reste au champ ; 429 explique le délai ; réseau incertain réconcilie l’état avant retry. Texte lisible, labels visibles et aperçu clavier avant envoi.

## UX23 — Décider et rejoindre l’échange

**Routes :** /espace/coups-de-main (Reçus / Envoyés), /espace/coups-de-main/:id ; progression sur la fiche projet et événement sur le fil existant. Il s’agit de propositions ponctuelles, pas d’un chat privé. Montrer projet, résumé, date, état textuel et actions autorisées.

**Accepter :** aperçu du résumé consenti ; choix explicite « Créer un échange » ou « Rattacher à un échange ouvert ». En création, propriétaire relit les champs préremplis ; en rattachement, titre exact du fil visible. Seconde case non précochée de publication. Bouton « Accepter et ouvrir l’échange » puis navigation interne seulement après200. Aucun droit équipe/dépôt ajouté. Décliner : motif facultatif privé et sans pression. Retirer : proposant, pending uniquement. Expiration visible sans faux refus humain.

**États :** accepted signifie « Collaboration commencée », lien vers fil ; progression ultérieure vient des contributions réelles. Pending/declined/withdrawn/expired jamais dans le flux public. Annulation/réseau, 409 et session expirée ne perdent pas discrètement la saisie ; purge du cache privé à la déconnexion. Mobile une colonne, tableaux transformés en listes lisibles, pas de toast seul pour une erreur bloquante.
