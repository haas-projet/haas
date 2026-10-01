# HAAS — Skills de design recherchés et politique d’utilisation

**Consultation :** 30 septembre 2026. **Nature :** sélection de sources pertinentes, pas inventaire exhaustif de tous les skills existants. **Installation effectuée dans votre Codex :** aucune. Les huit skills HAAS du pack V1 étaient des instructions originales adaptées au projet ; les skills externes ci-dessous ne sont pas copiés dans l’archive.

## 1. Skills HAAS livrés dans le pack

Les dossiers `.agents/skills/haas-…/` contiennent un SKILL.md avec nom et description, et un fichier d’interface facultatif. Les quatre skills centrés sur la qualité d’interface sont haas-interface-design, haas-accessibility, haas-french-ux-writing et haas-ui-review. Les autres encadrent React, Laravel, commits et réception.

La documentation actuelle de Codex décrit les skills dans `.agents/skills` du dépôt et des niveaux parents. Ils peuvent être invoqués explicitement avec `$nom-du-skill` ou découverts selon leur description. Vérifier leur présence dans l’interface de votre version ; redémarrer la session si nécessaire. Certaines instructions externes anciennes peuvent encore mentionner un autre chemin d’installation : utiliser celui effectivement reconnu par votre environnement, sans copier aveuglément un chemin ancien.

Sources OpenAI : https://developers.openai.com/codex/skills ; https://developers.openai.com/codex/guides/agents-md.

## 2. Sélection externe vérifiée

| Source / dossier | Usage utile pour HAAS | Licence ou limite observée | Décision |
|---|---|---|---|
| Anthropic `frontend-design` | Direction visuelle, composition et finition non générique | LICENSE.txt du dossier consulté : Apache-2.0 | Optionnel pour la phase frontend ; la charte et l’accessibilité HAAS restent prioritaires |
| Vercel `web-design-guidelines` | Revue des conventions d’interface et d’interaction | README du dépôt déclare MIT ; le skill charge un document distant de règles | Lire le contenu et sa révision avant usage, pas de fetch exécuté aveuglément |
| Vercel `react-best-practices` | Performance et structure React | Manifeste `vercel-react-best-practices`, licence MIT déclarée | Sélectionner les règles SPA pertinentes ; ne pas introduire Next.js/RSC/SWR dans HAAS |
| Vercel `composition-patterns` | Composition des composants et interfaces réutilisables | Manifeste `vercel-composition-patterns`, licence MIT déclarée | Utile pour éviter props et composants monolithiques ; pas de complexité gratuite |
| OpenAI `playwright` | Pilotage de navigateur, vérifications d’écrans et interactions | LICENSE.txt consulté : Apache-2.0 ; approche CLI | Complète les tests du produit, ne les remplace pas ; navigateur disponible requis |
| OpenAI `screenshot` | Capture de l’affichage quand une preuve visuelle est nécessaire | Conditions du dossier à relire à la révision installée | Optionnel ; captures réelles et sans données sensibles |
| OpenAI `figma` | Lire un design fourni et exploiter son contexte | Licence du dossier et connexion Figma à vérifier | Pas nécessaire actuellement : aucun fichier Figma de référence fourni |
| OpenAI `figma-implement-design` | Transformer un design Figma identifié en interface | LICENSE.txt soumis aux Figma Developer Terms, conditions spécifiques, pas licence permissive présumée | Optionnel seulement après vérification des droits, de l’accès et de la compatibilité concours |
| OpenAI `figma-create-design-system-rules` | Formaliser les correspondances entre maquette et code | Licence et conditions propres au dossier à vérifier | Utile seulement avec un besoin Figma réel ; le design system HAAS existe déjà dans ce pack |

Une déclaration MIT dans un README ne vaut pas un audit complet des fichiers et dépendances. Les fichiers de licence et sous-dossiers sont revérifiés au commit effectivement installé. En particulier, ne pas déduire de l’organisation GitHub hébergeant un skill que tous ses dossiers ont la même licence.

## 3. Sources directes

### Anthropic
- Dossier : https://github.com/anthropics/skills/tree/main/skills/frontend-design
- Manifeste : https://raw.githubusercontent.com/anthropics/skills/main/skills/frontend-design/SKILL.md
- Licence consultée : https://raw.githubusercontent.com/anthropics/skills/main/skills/frontend-design/LICENSE.txt

### Vercel
- Dépôt et README : https://github.com/vercel-labs/agent-skills
- Revue UI : https://github.com/vercel-labs/agent-skills/tree/main/skills/web-design-guidelines
- Règles chargées par ce skill : https://github.com/vercel-labs/web-interface-guidelines/blob/main/command.md
- React : https://github.com/vercel-labs/agent-skills/tree/main/skills/react-best-practices
- Composition : https://github.com/vercel-labs/agent-skills/tree/main/skills/composition-patterns

### OpenAI — navigateur et Figma
- https://github.com/openai/skills/tree/main/skills/.curated/playwright
- https://github.com/openai/skills/tree/main/skills/.curated/screenshot
- https://github.com/openai/skills/tree/main/skills/.curated/figma
- https://github.com/openai/skills/tree/main/skills/.curated/figma-implement-design
- https://github.com/openai/skills/tree/main/skills/.curated/figma-create-design-system-rules
- Conditions Figma observées : https://raw.githubusercontent.com/openai/skills/main/skills/.curated/figma-implement-design/LICENSE.txt

Ces liens pointent vers les sources publiques ; `main` est mouvant. Aucune révision immuable n’est prétendue approuvée dans ce pack. Avant installation, enregistrer le commit réel, la licence et la date de revue dans le registre.

## 4. Installation contrôlée, facultative

Ne pas installer neuf skills pour chaque écran. Commencer par les skills HAAS ; sélectionner ensuite un complément répondant à un besoin concret. Une skill est une instruction et peut inclure des scripts : la lire comme du code tiers avant de l’exécuter.

Prompt possible à donner au skill-installer de Codex, après choix humain :

```text
Inspecte d’abord le skill sélectionné dans sa source officielle.
Montre son périmètre, ses scripts, ses accès réseau et sa licence.
Relève le commit source réel et les dépendances nécessaires.
N’exécute pas de script distant et n’écrase aucun skill existant.
Attends ma confirmation avant installation et conserve la provenance.
```

Le mécanisme `$skill-installer` dépend de sa disponibilité dans l’environnement Codex. Ne pas prétendre l’avoir appelé depuis le présent document. Aucun achat, connexion Figma, envoi de code vers un service ou publication de dépôt n’est autorisé par la simple présence d’un lien dans ce catalogue.

## 5. Adaptation au concours

L’article 6 transmis impose des conditions de composants et licences. Leur périmètre exact pour les outils de développement/services externes reste à clarifier ; ne pas convertir cette réserve en conclusion juridique sur chaque skill. Conserver le registre runtime / développement / service externe et ne pas distribuer un contenu tiers sous une licence non vérifiée.

L’instruction externe « rendre le design original » ne doit jamais supprimer des labels, réduire un contraste, créer de fausses preuves ou changer le périmètre P0. L’ordre backend puis frontend demeure prioritaire. Les skills servent à renforcer une tâche, pas à redéfinir le produit.


## Mise à jour antérieure du catalogue

Le pack contient désormais **dix skills HAAS originaux**, dont collaborative-verification et help-offers. La sélection de neuf pistes externes ci-dessus garde sa date du 30 septembre 2026 : elle n’a pas fait l’objet d’une nouvelle revue exhaustive dans cette mise à jour. Les conditions et versions doivent être contrôlées avant installation. Les références officiellement relues le 1er octobre figurent dans `docs/sources/REFERENCES_PRODUIT.md`.


## Portée de cette consolidation
Le mail de cadrage fourni est la base du changement produit. Les références externes ci-dessus sont conservées avec leur date antérieure ; elles n’ont pas été revalidées pour cette mise à jour rédactionnelle. Vérifier versions, conditions et coûts au moment d’implémenter ou acheter.
