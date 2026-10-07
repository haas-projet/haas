# Capsules — contrat du schéma B22

## Périmètre

Le schéma conserve les capsules documentaires, leurs versions, les attributions, les technologies et les métadonnées d'artefacts. Il ne livre ni publication HTTP, ni kit, ni laboratoire. Les services B23/B24/B25 vérifient les droits et l'état courant ; les contraintes PostgreSQL constituent une protection supplémentaire contre une écriture directe incohérente.

- Une capsule référence exactement une demande existante ou une origine éditoriale explicite. La FK de source interdit sa suppression ; la cohérence « demande résolue et accessible » appartient au service B23, sous verrou, pas à une FK.
- `slug` est normalisé et unique ; `(capsule_id, version_label)` est unique.
- `lock_version` démarre à 1 et reste positif. L'incrément et la détection 409 relèvent du service de brouillon.
- `capsule_version_technologies` associe chaque version à une technologie réelle et à une compatibilité déclarée facultative. Sa clé composite interdit les doublons. Les migrations de ce pivot et du verrou reprennent les chemins B23 existants pour éviter un schéma parallèle.
- Les rôles de contribution sont `diagnosis`, `fix`, `documentation`, `test`, `case` ; un même membre peut avoir plusieurs rôles distincts, chacun unique par version.

## Publication et historique

`published` et `withdrawn` exigent une date et un relecteur. Les autres états ne portent pas de date de publication. Une version publiée peut devenir `withdrawn` en conservant sa date, son corps, ses limites et sa revue. Le retour au brouillon, la réécriture documentaire et la suppression — y compris par cascade du parent — sont refusés par PostgreSQL. Une correction crée une nouvelle version ; elle ne remplace pas l'ancienne.

Le relecteur ne peut être ni le propriétaire de la capsule, ni un contributeur de cette version. La FK `reviewer_id` passe à `RESTRICT` pour préserver cette attribution. Les services B24/B25 doivent aussi vérifier l'habilitation, le compte actif et la décision de revue : le schéma ne constitue pas une approbation humaine.

Après publication, provenance, propriétaire et slug du parent restent stables. Technologies et contributions de la version sont figées. La visibilité de la capsule et le passage publié → retiré demeurent possibles pour la modération future ; ces opérations ne changent pas le contenu publié. L'ordre des verrous d'un service doit être acteurs → capsule → version → liens, avec des IDs triés lorsque plusieurs acteurs ou versions sont concernés. Les triggers des liens verrouillent leur version avant de vérifier sa publication.

Les modèles n'acceptent en assignation de masse que le contenu utilisateur (`slug`, `editorial_origin`, `version_label`, `body`, `limits`). Identité, provenance issue d'une demande, état, visibilité, revue, date, verrou, attribution et chemins/empreintes/approbations d'artefacts sont renseignés explicitement par des commandes serveur autorisées. Les factories de tests restent explicites et n'accordent aucun droit HTTP.

## Migrations et reprise

Les migrations initiales partagées sont conservées. La migration additive `2026_10_07_200000_b22_preserve_published_capsule_versions.php` remplace le CHECK incompatible avec le retrait, protège le contenu publié et renforce le verrou et l'attribution. Son rollback refuse les versions retirées : l'ancien schéma ne peut représenter leur historique. Cette erreur n'efface aucune version. Le rollback complet de tables vides reste testé ; les tests de migration des domaines parents déposent les tables de capsules, enfants avant parents, sur leur base dédiée.

Ces garanties SQL ne valident pas AC12/AC13 complets, qui requièrent les parcours B24/B25 et une revue humaine. Aucun endpoint B22 n'est ajouté à OpenAPI, aucun GO_FRONTEND ou BACKEND_GATE n'est obtenu par ce schéma.
