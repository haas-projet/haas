# HAAS — Budget simplifié
**Tarifs publics consultés le 1er octobre 2026.** Ce tableau n'est pas un devis ni une autorisation d'achat. Les prix du panier, la TVA, les options et l'éligibilité à la remise priment.

## Infrastructure Systalink
| Poste | Référence publique | Tarif normal / mois | Avec 40 % de remise, si éligible |
|---|---|---:|---:|
| VPS Business | 2 vCPU, 8 Go RAM, 100 Go NVMe | 31 486 FCFA | 18 891,60 FCFA |
| Stockage distant Starter | 250 Go | 3 280 FCFA | 1 968 FCFA |
| **Sous-total Systalink** | Un VPS et sauvegardes | **34 766 FCFA** | **20 859,60 FCFA** |

Calcul : `(31 486 + 3 280) × 0,60`. Pour deux mois, hypothèse prudente : un mois remisé puis un mois normal, soit **55 625,60 FCFA**. Si seule l'instance et pas le stockage est remisée, premier mois **22 171,60 FCFA** et deux mois **56 937,60 FCFA**. Sans remise du tout, deux mois **69 532 FCFA**. Les 40 % proviennent du règlement fourni, non d'un panier inspecté. Les 70 % après résultats ne sont pas cumulés ni anticipés. [R, art. 9 ; D01, D02]

## Vercel et qualité
| Poste | Hypothèse budgétaire | Réserve |
|---|---|---|
| Vercel Hobby | 0 USD seulement si conditions d'usage et de dépôt respectées | Pas une gratuité validée pour l'équipe ou le concours. |
| Vercel Pro, 1 siège déployeur | 20 USD/mois ; 40 USD pour 2 mois | Un siège est inclus ; compatibilité des contributions Git à vérifier. |
| Vercel Pro, 2 sièges déployeurs | 40 USD/mois ; 80 USD pour 2 mois | Ce n'est pas une exigence automatique : dépend du mode de collaboration autorisé. |
| Qodana Ultimate existant | Aucun nouvel achat budgété | Déclaration utilisateur ; vérifier que le projet et les contributeurs sont couverts. |
| GitHub Actions | Selon forfait et consommation | Ne pas présumer les minutes privées illimitées. |

Hobby est réservé à l'usage personnel non commercial ; une organisation GitHub privée ne peut pas y être déployée via l'intégration documentée. Pro facture un forfait de 20 USD et les sièges déployeurs supplémentaires à 20 USD, hors taxes et consommations additionnelles. Ne pas contourner ces règles en publiant le dépôt ou en changeant l'auteur des commits. [D04, D05, D08]

## Ce qui reste à chiffrer
Domaine, envois SMTP/API, taxes, adresses/options facturées, dépassements, éventuel environnement de recette temporaire, travail d'administration et marge de sécurité. Le service de base de données managé, un deuxième VPS LAB, une préproduction permanente et un nouveau chatbot ne figurent pas dans la cible initiale.

**Enveloppe de base pour 2 mois :** 55 625,60 FCFA Systalink, **plus** le forfait Vercel admissible et les postes ci-dessus. Les USD restent séparés des FCFA tant qu'aucun taux/frais de paiement n'est confirmé. Une provision de 15 % est un choix budgétaire, pas de la TVA calculée.

**Aucune garantie de charge :** avant l'ouverture, vérifier que cette petite instance assure le parcours principal pendant un run. Les restrictions du lab ne sont pas supprimées pour faire rentrer le produit dans une offre moins chère.
