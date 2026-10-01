# HAAS — Contrastes des tokens de référence

Exécution : 2026-10-01T20:57:13.305Z. Paires : 22. Réussies : 22. Échecs : 0.

Calcul sRGB opaque / luminance relative. Ce contrôle porte sur les paires définies, pas sur une application rendue ni sur une conformité WCAG complète. Opacité, dégradé et couleurs héritées doivent être vérifiés dans le navigateur.

| Usage | Premier plan | Arrière-plan | Rapport | Minimum | Résultat |
|---|---|---|---:|---:|---|
| Texte principal sur paper | #102A2E | #F5F7F4 | 13.9970:1 | 4.5:1 | PASS |
| Texte principal sur surface | #102A2E | #FFFFFF | 15.0790:1 | 4.5:1 | PASS |
| Texte principal sur mint | #102A2E | #BFE8D5 | 11.2868:1 | 4.5:1 | PASS |
| Texte secondaire sur paper | #506367 | #F5F7F4 | 5.8684:1 | 4.5:1 | PASS |
| Texte secondaire sur surface | #506367 | #FFFFFF | 6.3221:1 | 4.5:1 | PASS |
| Bouton blanc sur primary | #FFFFFF | #087F73 | 4.8910:1 | 4.5:1 | PASS |
| Bouton blanc sur primaryHover | #FFFFFF | #06695F | 6.5756:1 | 4.5:1 | PASS |
| Lien sur paper | #06695F | #F5F7F4 | 6.1038:1 | 4.5:1 | PASS |
| Lien sur surface | #06695F | #FFFFFF | 6.5756:1 | 4.5:1 | PASS |
| Lien sur mint | #06695F | #BFE8D5 | 4.9219:1 | 4.5:1 | PASS |
| Bordure fonctionnelle sur paper | #718782 | #F5F7F4 | 3.5506:1 | 3:1 | PASS |
| Anneau de focus sur paper | #087F73 | #F5F7F4 | 4.5400:1 | 3:1 | PASS |
| Bordure fonctionnelle sur surface | #718782 | #FFFFFF | 3.8251:1 | 3:1 | PASS |
| Anneau de focus sur surface | #087F73 | #FFFFFF | 4.8910:1 | 3:1 | PASS |
| État warning | #8A4B10 | #FFF4E5 | 6.2444:1 | 4.5:1 | PASS |
| Texte warning sur blanc | #8A4B10 | #FFFFFF | 6.7861:1 | 4.5:1 | PASS |
| État danger | #B42318 | #FFF1F0 | 5.9771:1 | 4.5:1 | PASS |
| Texte danger sur blanc | #B42318 | #FFFFFF | 6.5743:1 | 4.5:1 | PASS |
| État info | #1D4ED8 | #EFF6FF | 6.1580:1 | 4.5:1 | PASS |
| Texte info sur blanc | #1D4ED8 | #FFFFFF | 6.7016:1 | 4.5:1 | PASS |
| Bouton destruction | #FFFFFF | #B42318 | 6.5743:1 | 4.5:1 | PASS |
| Badge positif | #102A2E | #BFE8D5 | 11.2868:1 | 4.5:1 | PASS |

Seuils documentaires : texte courant 4,5:1 ; limites fonctionnelles non textuelles 3:1. Les cibles et l’état de focus exigent aussi une revue contextuelle.
Sources : https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html ; https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html

