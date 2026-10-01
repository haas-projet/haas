# DEPLOYMENT_GATE — HAAS

**Statut initial : NON EXÉCUTÉ.** Aucun résultat applicatif, serveur ou compte fournisseur vérifié par ce pack. **Autorisation : GO_PRODUCTION à obtenir de l’utilisateur**, après revue du second développeur.

Les tests de configuration peuvent démarrer avant le frontend ; les essais réels de domaines et de navigateur se terminent en R03. Ces derniers ne peuvent pas être cochés par simple lecture d’un fichier.

| ID | Contrôle | Attendu | État / preuve |
|---|---|---|---|
| DEP-AC01 | Offre et facture | Produit Datacloud actif, achat éligible, backend réellement hébergé ; configuration, taxe et remise vérifiées. | NON EXÉCUTÉ |
| DEP-AC02 | TLS et session | SPA Vercel et API Systalink : connexion, lecture/écriture, CSRF et déconnexion réussis sur les hôtes définitifs. | NON EXÉCUTÉ |
| DEP-AC03 | Origine non autorisée | CORS/CSRF refusent une origine non approuvée ; aucune autorisation wildcard de previews. | NON EXÉCUTÉ |
| DEP-AC04 | Cookies et B2 | Aucun cookie HAAS envoyé aux hôtes B2 ; API B2 limitée aux données fictives. | NON EXÉCUTÉ |
| DEP-AC05 | Runner et secrets | Sous le compte LAB : lecture .env, secrets APP et sauvegardes impossible. | NON EXÉCUTÉ |
| DEP-AC06 | Rôles PostgreSQL | Compte LAB refusé sur haas_app ; absence de droits super-utilisateur/propriétaire ; fixtures isolées. | NON EXÉCUTÉ |
| DEP-AC07 | Concurrence et quota | Un seul run global ; comparaison réserve deux unités ; double clic/rejeu ne double pas la réservation. | NON EXÉCUTÉ |
| DEP-AC08 | Panne et charge | Runner tué/ralenti : aucun faux succès ; API encore disponible ; latences et ressources mesurées. | NON EXÉCUTÉ |
| DEP-AC09 | Vercel et assets | Rafraîchissement des routes, assets, 404 applicative, bonne API et aucune variable VITE secrète. | NON EXÉCUTÉ |
| DEP-AC10 | Chaîne de livraison | Pas de publication avant tests/revue ; backend compatible avant frontend ; SHA et empreintes vérifiables. | NON EXÉCUTÉ |
| DEP-AC11 | Reprise | Sauvegarde restaurée hors production ; périmètre et temps mesurés ; récupération des clés autorisée. | NON EXÉCUTÉ |
| DEP-AC12 | Retour de release | Ancienne version frontend/API compatible restaurée sans perdre de données ; workers rechargés. | NON EXÉCUTÉ |
| DEP-AC13 | Alertes | Indisponibilité, TLS, disque, queue et sauvegarde : alertes reçues par les responsables. | NON EXÉCUTÉ |
| DEP-AC14 | Accords et accès | Ultimate actif sur le projet, Vercel/dépôt/sièges admissibles, aucune fonction Plus présumée ; GO_PRODUCTION humain. | NON EXÉCUTÉ |

Pour chaque ligne : date, commit, environnement, étapes, attendu/observé, lien de rapport et relecteur. Un contrôle documentaire passé ne prouve pas ce test serveur. Les cases cochées dans le dépôt existant ne sont jamais effacées ; réévaluer seulement les changements V3.
