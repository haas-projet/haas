# HAAS — Système de design et ergonomie

**Direction :** un atelier d’entraide calme et lisible. **Priorité :** comprendre le problème, identifier l’action et lire la preuve. **Statut :** spécification à implémenter après le gate backend.

La palette, la police système et les principes fondamentaux sont issus du cahier de référence §19–20. Les tokens complémentaires ci-dessous sont des décisions de conception de ce pack, pas des exigences attribuées à l’organisateur.

## 1. L’identité du produit

Un fond papier légèrement teinté, des surfaces blanches, une encre sombre et une action vert-pétrole. La menthe accompagne les informations positives, sans remplacer le texte. L’interface a une personnalité grâce à sa hiérarchie, ses contenus et ses parcours, pas grâce à des effets qui gênent la lecture.

HAAS et sa signature doivent être remplaçables par configuration. Le wordmark peut rester typographique. Aucun logo de tiers ne doit suggérer une validation officielle. Ne pas ajouter d’illustrations décoratives lourdes, de graphiques commerciaux ni de compteurs d’usage inventés.

## 2. Tokens et usages autorisés

| Token | Valeur | Utilisation |
|---|---|---|
| ink | #102A2E | Texte, titres, icônes informatives sur les fonds clairs |
| paper | #F5F7F4 | Arrière-plan général |
| surface | #FFFFFF | Cartes, champs, zones de lecture |
| primary | #087F73 | Bouton principal avec texte blanc |
| primaryHover | #06695F | Survol du bouton et liens soulignés |
| mint | #BFE8D5 | Fond doux, accompagné de texte ink |
| muted | #506367 | Métadonnées lisibles, sans opacité ajoutée |
| borderStrong | #718782 | Limite visible des champs et contrôles |
| line | #DCE5DF | Séparateur décoratif uniquement |
| warning / warningBg | #8A4B10 / #FFF4E5 | Avertissement explicite |
| danger / dangerBg | #B42318 / #FFF1F0 | Erreur, suppression ou retrait |
| info / infoBg | #1D4ED8 / #EFF6FF | Information neutre |

La couleur ne suffit jamais à exprimer l’état : associer un libellé et, lorsque pertinent, une icône. Les paires effectivement autorisées figurent dans contrast-pairs.json. Le script de contrôle calcule les rapports sans arrondir avant comparaison.

**Deux pièges interdits :** le texte primary sur mint n’atteint pas le contraste de texte courant recherché ; utiliser ink ou primaryHover. La ligne #DCE5DF sur blanc est trop faible pour être le seul repère d’un champ ; utiliser borderStrong. Ne pas réduire l’opacité de texte pour obtenir un style « discret ».

## 3. Typographie et lecture

Pile système : system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif. Aucun fichier de police à télécharger. Corps 16 px au minimum dans la configuration de base ; interligne 1,6. Les métadonnées secondaires peuvent utiliser 14 px, jamais le message essentiel, le contenu technique ou les erreurs. Les unités rem préservent le zoom et les préférences utilisateur.

Titres suggérés : h1 28–40 px selon écran, h2 24–28 px, h3 18–20 px. Utiliser les titres pour la structure, pas seulement pour la taille. Un seul h1 par écran. Longueur de ligne de lecture cible : 60–75 caractères. Pas de texte justifié, de paragraphes tout en capitales ni de coupe irréversible d’un message d’erreur.

Le code utilise une pile monospace système, taille lisible et défilement horizontal limité au bloc. Un bouton « Copier le code » possède un nom accessible et confirme seulement la réussite réelle de la copie. Ne pas utiliser de coloration syntaxique où les commentaires deviennent illisibles. Aucun HTML utilisateur exécuté.

## 4. Disposition

Conteneur global maximal 1 200 px. Colonne de lecture maximale 72ch. Formulaire maximal 720 px. Marges mobiles de 16 px. Espacements réguliers 8/16/24/32/48 px, avec 4/12 px pour les détails nécessaires. Arrondi carte 12 px ; champs 8 px ; dialogue 16 px. Ombre légère facultative ; le contraste ne dépend pas de l’ombre.

Navigation principale : Explorer, Demander de l’aide, Mes contributions. Connexion est visible pour un visiteur ; notifications et profil pour un membre. Administration n’apparaît que pour les personnes autorisées. Pas de barre latérale permanente à dix entrées pour un visiteur qui cherche simplement une solution.

Écrans de lecture : contenu à gauche, résumé/preuves à droite sur grand écran ; une seule colonne sur mobile. Les textes peuvent grandir sans imposer de hauteur fixe. Les tableaux de laboratoire deviennent des cas empilés sur mobile. Le code peut défiler, jamais toute la page.

## 5. Composants et comportements

**Button.** Un bouton principal par bloc d’action. Texte explicite avec verbe : « Publier la demande », pas « Valider » partout. Hauteur/cible confortable de 44 px proposée pour HAAS. Pending montre le travail en cours et prévient le double clic, sans déplacer brutalement la mise en page. Une désactivation importante s’explique à proximité ; pas de tooltip accessible uniquement à la souris.

**Field.** Label persistant, aide facultative, caractère obligatoire explicite, erreur reliée au champ. Aucun placeholder utilisé comme seul label. Conserver les saisies après 422/409 et après perte réseau. Sur erreur de soumission : résumé annoncé et focus sur l’erreur pertinente. Ne pas afficher le rouge à chaque frappe avant que l’utilisateur ait pu terminer.

**Card.** Titre lisible, technologie, état et date. Pas de carte totalement cliquable contenant des contrôles imbriqués qui se concurrencent. Le titre porte le lien ; favori et menu sont des boutons séparés.

**Dialog.** Titre, objectif, conséquence et actions nommées. Focus déplacé dans le dialogue puis restauré à la fermeture, Échap lorsque cela est approprié, ordre clavier cohérent. Une confirmation destructive nomme la ressource et demande le motif prévu par le backend.

**Badge.** « Acceptée par le demandeur », « Retour déclaré » et « Test du laboratoire » sont distincts. Afficher version et date pour une preuve. Un pictogramme de bouclier ne doit pas faire croire à une certification de sécurité.

**Feedback.** Les messages importants restent dans le contexte de l’action. Une notification fugace seule ne doit pas porter une erreur de formulaire, une perte de données ou un échec de test. `aria-live` est ciblé et mesuré, sans lire de nouveau toute une discussion toutes les 15 secondes.

## 6. Cinq états obligatoires

Chaque écran prévoit chargement, absence de contenu, succès/données, erreur et accès interdit. Ajouter les états métier : en revue, retiré, suspendu, conflit, en file, échec et délai dépassé lorsqu’ils existent. Ne pas montrer « Aucun résultat » pendant le chargement. Ne pas confondre une indisponibilité technique et une recherche vide.

Conserver le contexte lors des erreurs. Un filtre se retire par une action claire. Une demande sans réponse invite à compléter le contexte ou à revenir, pas à promettre un délai d’aide. Une reprise réseau ne doit jamais créer une seconde opération invisible.

## 7. Microcopie française

| Éviter | Préférer |
|---|---|
| Submit / Success / Error | Publier / Enregistré / Échec de l’envoi |
| Code sûr et certifié | Tests réussis pour la version 1.0.0 |
| Offline sync done | Confirmée par le serveur — seulement après réponse |
| Utilisateur interdit | Vous n’avez pas l’autorisation d’effectuer cette action. |
| Aucune donnée | Aucune demande ne correspond à ces filtres. |
| Réessayez ! | La connexion a été interrompue. Vérifiez l’état de l’envoi avant de reprendre. |

Écrire un message qui décrit la situation et une action utile, sans culpabiliser. Pas de fausse certitude : « Conservée sur cet appareil » ne signifie pas « sauvegardée dans le cloud ». Expliquer « capsule » une première fois : « une solution documentée et versionnée ».

## 8. Accessibilité vérifiable

Cible WCAG 2.2 AA, pas une certification présumée. Texte courant au moins 4,5:1 ; repères non textuels nécessaires au contrôle au moins 3:1 contre les couleurs adjacentes pertinentes. Les 44 px sont notre objectif de confort, distinct du critère AA 24 px et de ses exceptions.

Tester clavier complet, ordre de focus, liens d’évitement, focus jamais caché derrière un en-tête, zoom 200 %, largeur 320 CSS px et grands textes. Pour le focus, vérifier le contour et son fond réel, y compris autour d’un bouton coloré ; un outline avec offset évite de le confondre avec la bordure. Choisir des éléments natifs avant une imitation div+click.

Utiliser les outils automatiques comme aide, puis vérifier manuellement les tâches, noms accessibles, dialogues, annonces et lecture. Documenter les technologies d’assistance réellement testées. Ne pas annoncer « compatible tous lecteurs d’écran » sans preuve.

## 9. Animation et performance

Transitions de 120–180 ms si elles rendent un changement compréhensible. Respecter prefers-reduced-motion. Pas de fond animé, autoplay, curseur personnalisé, scroll forcé ou animation retardant la tâche. Les skeletons ne clignotent pas inutilement. Aucun mode sombre P0 imposé : mieux vaut un thème clair fini que deux thèmes incomplets.

Chargement différé des pages lourdes, absence d’images inutiles et édition de code légère. Le catalogue doit rester utilisable sur la connexion de test définie au cahier. Les objectifs de temps et de poids se mesurent sur le build réel, pas sur une estimation de design.

## 10. Revue visuelle de réception

Préparer des contenus longs, un titre court, un titre de 140 caractères, une erreur réseau, un compte sans contribution, un résultat sans laboratoire et une version retirée. Vérifier 360/390/768/1280 px, puis 320 CSS px pour le reflow et le zoom 200 %. Capturer les écrans réels et les ouvrir avant d’affirmer qu’ils sont lisibles.

Pour chaque défaut : écran, viewport, étape, impact sur la tâche, correctif, nouvelle capture. Comparer avant/après au même état. Les preuves ne doivent contenir ni secret ni donnée personnelle réelle.

## Références externes

W3C : https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html ; https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html ; https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html.
Playwright : https://playwright.dev/docs/accessibility-testing. Les autres valeurs de ce document sont des choix HAAS, non des exigences prétendument prescrites par ces sources.

## Signature de l’atelier (parcours complémentaire)
Dans les écrans de l’atelier : un cas, deux observations comparables et une contribution reliée. Priorité locale à la fiche et aux limites ; à l’accueil, priorité à la communauté, aux projets et aux échanges. Le contraste mesuré des tokens est conservé. Le logo dans assets/brand est un PNG fourni par l’équipe, sans promesse de disponibilité juridique. Les maquettes du PowerPoint sont des schémas de conception et doivent être remplacées par captures de l’application dans une soutenance après développement.


## Priorité communautaire issue du mail

La palette, les contrastes, la typographie et les composants restent identiques. L’accueil privilégie les projets, les échanges et la découverte des personnes. Les résultats de laboratoire apparaissent au bon endroit dans une capsule, pas comme des scores de confiance sur tous les membres.

Libellés de navigation : Explorer, Projets, Développeurs ; bouton Demander de l’aide ; menu compte Mon espace/Mes contributions/Notifications. Mobile : un menu principal nommé, liens et focus restauré ; éviter une nouvelle barre pour chaque module.

Nouvelles cartes : projet (titre, résumé, technologies, phase déclarée, besoin), développeur (pseudo, bio, stack, disponibilité déclarée). Réutiliser mêmes espacements/contrastes/states. Un projet sans capture ou sans démo reste présentable. Interface française sans code obligatoire pour poser une question. UX18–20 complètent le référentiel, elles ne sont pas des captures d’application existante.

## Rencontrer par un petit travail — F18

Ne pas ajouter une palette ou un tableau de bord. Réutiliser les tokens, boutons, états, listes et formulaires HAAS pour UX21–23. Une carte décrit un projet, un petit résultat utile et une prochaine action, sans notes d’expertise ni fausse durée. Le CTA de l’accueil « Donner un coup de main » ne doit pas masquer la possibilité de poser une simple question.

Deux consentements séparés, explicites et non précochés ; aperçu exact du résumé publiable ; offre en attente distincte de collaboration commencée et contribution réellement effectuée. Le sens des états est écrit, pas seulement coloré. Un refus ne reçoit pas d’illustration culpabilisante. Les badges « expirée » et « refusée » ne sont jamais confondus. Carte sans besoin = orientation vers une vraie alternative, pas un CTA mort.
