/** Documentation presentation only. Requires pptxgenjs. */
const pptxgen = require('pptxgenjs');
const path = require('path');
const fs = require('fs');
const root=path.resolve(__dirname,'..');
const pptx=new pptxgen();pptx.layout='LAYOUT_WIDE';pptx.author='Équipe Delta';pptx.subject='HAAS — Communauté et rencontre par le coup de main';pptx.title='HAAS — Un projet, un coup de main, une rencontre utile';pptx.company='HAAS';pptx.lang='fr-FR';
pptx.theme={headFontFace:'Aptos Display',bodyFontFace:'Aptos',lang:'fr-FR'};
const C={ink:'102A2E',teal:'087F73',mint:'BFE8D5',paper:'F5F7F4',white:'FFFFFF',muted:'486064',line:'DCE5DF',warn:'8A4B10',rose:'FAF0E8'};
const logo=path.join(root,'assets/brand/haas-logo.png');const icon=path.join(root,'assets/brand/haas-icon.png');
const S=pptx.ShapeType;const notes=[];
function rect(sl,x,y,w,h,fill=C.white,line=C.line,r=.14){sl.addShape(r?S.roundRect:S.rect,{x,y,w,h,rectRadius:r,fill:{color:fill},line:{color:line,width:.8},radius:r});}
function tx(sl,text,x,y,w,h,size=22,color=C.ink,bold=false,extra={}){sl.addText(text,{x,y,w,h,fontFace:'Aptos',fontSize:size,color,bold,margin:0,breakLine:false,paraSpaceAfterPt:0, valign:'mid',...extra});}
function line(sl,x1,y1,x2,y2,color=C.teal,width=1.8,arrow=false){sl.addShape(S.line,{x:x1,y:y1,w:x2-x1,h:y2-y1,line:{color,width,...(arrow?{beginArrowType:'none',endArrowType:'triangle'}:{})}});}
function pill(sl,text,x,y,w,fill=C.mint,color=C.ink){rect(sl,x,y,w,.36,fill,fill);tx(sl,text,x+.12,y+.02,w-.24,.3,11.5,color,true);}
function base(kicker,n){let sl=pptx.addSlide();sl.background={color:C.paper};tx(sl,'HAAS',.56,.29,1.25,.32,19,C.ink,true);tx(sl,kicker.toUpperCase(),2.0,.32,10.65,.25,10,C.teal,true,{charSpacing:1.6});line(sl,.56,6.98,12.77,6.98,C.line,1);tx(sl,'ÉQUIPE DELTA  ·  CADEV 2026',.56,7.11,7.0,.17,9,C.muted);tx(sl,String(n).padStart(2,'0'),12.1,7.08,.64,.22,11,C.teal,true,{align:'right'});return sl;}
function title(sl,t,sub){tx(sl,t,.62,1.0,12.05,1.10,35,C.ink,true,{valign:'top',breakLine:false});if(sub)tx(sl,sub,.65,2.14,11.95,.64,18,C.muted,false,{valign:'top'});}
function note(sl,title,text){sl.addNotes(`${title}\n\n${text}`);notes.push({title,text});}
// 1 — identity/community promise
{
const sl=base('Une communauté pour les développeurs',1);sl.addImage({path:logo,x:.62,y:1.05,w:3.55,h:1.30});
tx(sl,'Un projet vous plaît ?\nDonnez un coup de main.\nConstruisez ensemble.',.64,2.73,8.05,2.08,36,C.ink,true,{valign:'top',paraSpaceAfterPt:6});
tx(sl,'Une première aide concrète.\nUne rencontre qui fait avancer.',.66,5.17,7.35,.85,23,C.muted);
for(const [i,h,d] of [[0,'Projets','Montrer ce que l’on construit'],[1,'Coups de main','Proposer une aide concrète'],[2,'Connaissances','Apprendre les uns des autres']]){let y=2.48+i*1.10;rect(sl,9.0,y,3.63,.86);tx(sl,h,9.23,y+.14,3.12,.28,20,C.teal,true);tx(sl,d,9.23,y+.48,3.12,.19,12.8,C.muted);}
tx(sl,'Projet de concours · Conception à développer et à valider',.67,6.48,10.8,.22,11,C.muted);
note(sl,'Ouverture — 25 secondes','Nous proposons HAAS, Help as a Service : une communauté où présenter ses projets, trouver de l’aide et construire ensemble. Nous partons des usages décrits dans le mail de l’organisateur. La rencontre peut partir d’un coup de main : projet volontairement ouvert, offre concrète, accord explicite du propriétaire, puis échange contextualisé. Notre atelier approfondit certains échanges, sans être obligatoire. Le nom est provisoire pour le concours. Ce support présente un produit conçu : ne pas annoncer du code ou des utilisateurs déjà acquis. Source : mail fourni [M] et décisions de conception HAAS.');
}
// 2 — exact framing, not invented requirements
{
const sl=base('Le thème de l’organisateur',2);tx(sl,'Le point de départ :\nla communauté.',.64,1.05,11.9,1.06,37,C.ink,true,{valign:'top'});
rect(sl,.65,2.45,12.0,1.40,C.ink,C.ink);tx(sl,"« Une plateforme d'échange pour les développeurs,\npar les développeurs et pour les développeurs. »",.98,2.68,11.34,.91,25,C.white,true);
const cards=[['Se retrouver','Découvrir des personnes\net leurs projets.'],['Échanger et apprendre','Poser des questions,\npartager des connaissances.'],['Construire ensemble','Collaborer autour\nde besoins concrets.']];
cards.forEach((a,i)=>{const x=.65+i*4.1;rect(sl,x,4.35,3.8,1.58);tx(sl,a[0],x+.22,4.56,3.34,.38,21,C.teal,true);tx(sl,a[1],x+.22,5.02,3.34,.63,19,C.ink);});
tx(sl,'Usages exprimés dans le mail ; leur mise en œuvre reste au choix des équipes.',.69,6.35,11.9,.32,12.5,C.muted);
note(sl,'Le cadrage — 25 secondes','Le mail de CADEV met en avant les développeurs qui se retrouvent, discutent, apprennent, montrent leurs projets et collaborent. Il laisse chaque équipe choisir comment créer cette expérience. Nos fiches projet, notre annuaire et notre atelier sont nos réponses à ce cadrage, pas une liste de modules imposés. Source : texte du mail fourni, expéditeur CADEV by Systalink, affiché le 30 septembre à 18:11 ; aucun accès à la messagerie.');
}
// 3 — core spaces
{
const sl=base('La réponse de HAAS',3);title(sl,'Tout part d’un projet\nou d’une question.');
const cards=[['01','Découvrir','Un projet intéressant.\nUn besoin bien défini.','PROJETS & PERSONNES'],['02','Proposer','Un petit coup de main.\nUn accord volontaire.','RENCONTRER'],['03','Construire','Un échange utile.\nUn résultat documenté.','CONTRIBUER & PARTAGER']];
cards.forEach((a,i)=>{let x=.65+i*4.10;rect(sl,x,2.7,3.8,3.15);tx(sl,a[0],x+.25,2.96,.65,.4,26,C.teal,true);tx(sl,a[1],x+.25,3.62,3.3,.46,28,C.ink,true);tx(sl,a[2],x+.25,4.25,3.3,.91,21,C.muted);pill(sl,a[3],x+.25,5.27,3.25,C.mint);});
tx(sl,'Ni code obligatoire pour poser une question, ni laboratoire imposé pour participer.',.70,6.32,11.9,.35,16,C.muted);
note(sl,'La proposition — 30 secondes','Trois portes d’entrée suffisent : je présente mon projet, je découvre des développeurs ou je pose une question. Les échanges utilisent les mêmes fils, commentaires et propositions. Une question de connaissance ne demande ni extrait de code, ni test, ni formulaire de panne. Les connaissances peuvent devenir des capsules réutilisables. Choix HAAS : F16 projets, F17 découverte, F02 question et F18 coup de main. Une offre en attente est privée aux deux personnes ; un résumé consenti est partagé après accord. Ni messagerie générale ni accès au dépôt.');
}
// 4 — native editable product sketches
{
const sl=base('Découvrir et participer',4);title(sl,'Trouver un point d’entrée\npour aider.');
rect(sl,.65,2.61,7.28,3.61);pill(sl,'PROJET · EXEMPLE FICTIF',.93,2.88,3.23);tx(sl,'Commande Facile',.95,3.45,6.57,.56,30,C.ink,true);tx(sl,'Une application de prise de commandes.',.95,4.10,6.5,.37,19,C.muted);pill(sl,'LARAVEL',.95,4.70,1.5,C.paper);pill(sl,'REACT',2.60,4.70,1.22,C.paper);pill(sl,'PROTOTYPE',3.98,4.70,2.0,C.paper);tx(sl,'Besoin : un premier retour sur le formulaire.',.95,5.28,6.58,.42,20,C.ink);rect(sl,.95,5.80,2.48,.30,C.teal,C.teal);tx(sl,'Je peux t’aider',1.10,5.82,2.18,.24,12.5,C.white,true);
rect(sl,8.23,2.61,4.4,3.61);tx(sl,'DÉVELOPPEUR',8.5,2.91,3.81,.3,12,C.teal,true,{charSpacing:1.4});tx(sl,'Moussa',8.50,3.54,3.82,.48,27,C.ink,true);tx(sl,'Laravel · React',8.50,4.16,3.82,.31,20,C.muted);tx(sl,'Je peux tester ce parcours',8.50,4.79,3.82,.39,20,C.teal,true);tx(sl,'Une proposition précise,\nà accepter par le propriétaire.',8.50,5.37,3.82,.51,15,C.muted);
tx(sl,'Projet ouvert aux coups de main → proposition → accord → échange public consenti.',.68,6.38,12.0,.29,14,C.muted);
note(sl,'L’expérience — 35 secondes','Cet écran est un schéma de principe avec des données fictives. Le propriétaire présente son projet et ouvre volontairement les coups de main, même sans demande initiale. Moussa propose un test limité. Le propriétaire accepte après aperçu public et crée ou choisit un fil. Deux consentements protègent la publication ; une offre n’ajoute aucune permission. Une demande existante peut toujours recevoir une contribution directe. L’annuaire est volontaire : il affiche des technologies et une disponibilité déclarée, pas une présence en ligne ni une expertise certifiée. Aucun dépôt de code n’est importé et aucun droit sur le dépôt externe n’est transféré par la fiche. Source : spécification HAAS communauté.');
}
// 5 — collaborative path
{
const sl=base('Un parcours cohérent',5);title(sl,'La rencontre commence\npar un premier coup de main.');
const stages=[['Un projet','A ouvre\nun petit besoin.'],['Une offre','B propose\nson aide.'],['Un accord','A accepte\nl’échange.'],['Un apport','B partage\nson retour.'],['Un partage','Le résultat\npeut resservir.']];
stages.forEach((a,i)=>{let x=.64+i*2.49;rect(sl,x,3.03,2.22,2.22);pill(sl,String(i+1).padStart(2,'0'),x+.18,3.22,.58);tx(sl,a[0],x+.18,3.80,1.88,.47,20,C.ink,true);tx(sl,a[1],x+.18,4.40,1.88,.61,17.4,C.muted);if(i<4)line(sl,x+2.23,4.12,x+2.46,4.12,C.teal,1.7,true);});
rect(sl,.65,5.70,12.0,.73,C.mint,C.mint);tx(sl,'Une question simple peut aussi recevoir une réponse, sans code ni test.',.94,5.9,11.40,.33,20,C.ink,true);
note(sl,'Le parcours — 30 secondes','A et B et C désignent des personnages de démonstration, pas trois membres de notre équipe. La chaîne relie un projet ouvert à une offre bornée, un accord, puis un apport et un partage. Le refus et l’absence de réponse ne sont pas pénalisés. Accepté signifie collaboration commencée, pas travail terminé. Les mêmes discussions sont utilisées ; aucune messagerie supplémentaire ni recommandation IA. Quand une acceptation est pertinente, elle revient à l’auteur de la demande. Une simple discussion peut aussi apporter une connaissance sans produire une capsule ou exécuter un laboratoire. Ce parcours devra être testé avec de vrais utilisateurs et leurs retours, pas présenté comme une adoption déjà acquise.');
}
// 6 — signature demonstration kept conditional
{
const sl=base('La fonctionnalité distinctive',6);title(sl,'Voir ce qu’une contribution\na changé.');
pill(sl,'EXEMPLE : UNE NOTIFICATION REÇUE DEUX FOIS',.66,2.45,7.05,C.mint);
rect(sl,.66,3.15,5.77,2.60,C.rose,'E6D7CA');tx(sl,'EXEMPLE PÉDAGOGIQUE INCORRECT',.96,3.43,5.15,.29,12,C.warn,true);tx(sl,'2 commandes',.96,4.00,5.15,.67,37,C.ink,true);tx(sl,'Le doublon produit un second effet.',.96,5.0,5.15,.39,19,C.muted);
rect(sl,6.74,3.15,5.90,2.60,C.white,C.teal);tx(sl,'VERSION CORRIGÉE À VÉRIFIER',7.04,3.43,5.28,.29,12,C.teal,true);tx(sl,'1 commande',7.04,4.00,5.28,.67,37,C.teal,true);tx(sl,'Le même événement n’est traité qu’une fois.',7.04,4.91,5.28,.55,18.5,C.muted);
tx(sl,'Même scénario, mêmes entrées, deux implémentations approuvées.',.7,6.03,12,.40,20,C.ink,true);tx(sl,'Résultats attendus de conception. À recalculer réellement ; toutes les capsules ne disposent pas d’un laboratoire.',.7,6.59,11.97,.20,10.5,C.muted);
note(sl,'La différence — 40 secondes','Pour une solution compatible, un membre apporte un cas à vérifier. L’équipe le traduit en scénario approuvé par une revue et une release, jamais en exécutant automatiquement le texte reçu. Sur B1, nous comparons un exemple pédagogique incorrect à une version corrigée, avec les mêmes entrées et des données fictives séparées. Les chiffres deux et un sont ici les résultats attendus à implémenter, pas des résultats déjà mesurés. Le logiciel livré devra les recalculer, montrer les échecs et conserver les versions et limites. Une comparaison n’est ni une certification de sécurité ni une intégration de paiement.');
}
// 7 — positioning vs AI
{
const sl=base('Avec les outils des développeurs',7);title(sl,'Gardez votre assistant.\nRetrouvez un collectif.');
rect(sl,.66,2.85,5.33,2.73);tx(sl,'VOTRE MANIÈRE DE TRAVAILLER',.96,3.17,4.75,.25,12,C.teal,true);tx(sl,'Votre projet.\nVos outils.\nVos idées.',.96,3.81,4.71,1.38,28,C.ink,true);
tx(sl,'+',6.2,3.72,.75,.76,40,C.teal,true,{align:'center'});
rect(sl,7.19,2.85,5.44,2.73,C.ink,C.ink);tx(sl,'LA COMMUNAUTÉ HAAS',7.50,3.17,4.83,.25,12,C.mint,true);tx(sl,'Un autre regard.\nDes cas concrets.\nUne mémoire partagée.',7.50,3.81,4.83,1.38,26,C.white,true);
tx(sl,'Notre valeur à vérifier : rendre les contributions extérieures faciles à trouver et à utiliser.',.70,6.22,11.94,.53,18,C.muted);
note(sl,'L’IA — 25 secondes','Nous ne demandons pas aux utilisateurs d’abandonner leur assistant. HAAS organise les échanges avec d’autres personnes, la découverte de projets et la mémoire des contributions. Nous ne fondons pas le projet sur l’idée que l’IA ne peut pas coder ou tester. Le bénéfice supplémentaire doit être observé au pilote. Aucun chatbot supplémentaire n’est requis dans le périmètre actuel. Il s’agit du positionnement HAAS, non d’une comparaison de performances ou d’un avantage démontré.');
}
// 8 — deployment & quality, one VPS
{
const sl=base('Une architecture maîtrisée à deux',8);title(sl,'Vercel pour l’interface.\nSystalink pour le backend.');
rect(sl,.65,2.73,3.46,2.64);tx(sl,'VERCEL',.93,3.03,2.91,.31,13,C.teal,true);tx(sl,'React\nTypeScript',.93,3.74,2.91,.87,29,C.ink,true);tx(sl,'Interface dans le navigateur',.93,4.93,2.91,.20,12,C.muted);
line(sl,4.16,4.09,5.15,4.09,C.teal,2,true);tx(sl,'API HTTPS',4.2,3.45,.92,.46,11.5,C.muted,false,{align:'center'});
rect(sl,5.2,2.73,4.24,2.64,C.ink,C.ink);tx(sl,'UN VPS SYSTALINK',5.5,3.03,3.62,.31,13,C.mint,true);tx(sl,'Laravel + PostgreSQL',5.5,3.67,3.60,.75,25,C.white,true);tx(sl,'Runner local restreint\nUne exécution à la fois',5.5,4.58,3.62,.54,16,C.white);
line(sl,9.48,4.09,10.06,4.09,C.teal,1.8,true);rect(sl,10.10,3.13,2.53,1.77,C.white,C.line);tx(sl,'SAUVEGARDE\nDISTANTE',10.33,3.41,2.06,.67,18.5,C.ink,true);tx(sl,'Chiffrée · à restaurer',10.33,4.4,2.06,.20,10.8,C.muted);
rect(sl,.65,5.72,11.98,.65,C.mint,C.mint);tx(sl,'GitHub Actions + Qodana Ultimate   ·   Tests   ·   Revue croisée   ·   Livraison contrôlée',.93,5.92,11.44,.22,15.5,C.ink,true);tx(sl,'Architecture cible. Un seul serveur, sans promesse de haute disponibilité ; configuration et isolation à vérifier.',.69,6.62,11.94,.18,10.4,C.muted);
note(sl,'Architecture — 40 secondes','Nous sommes deux développeurs Laravel et React. Le backend complet est testé avant le frontend, avec un GO_FRONTEND humain. Côté hébergement : un serveur Systalink/Datacloud pour Laravel, PostgreSQL et un runner local restreint ; le frontend React est sur Vercel. Le runner n’exécute que des scénarios approuvés, sans secrets applicatifs, avec une seule exécution simultanée. Les cookies et CORS utilisent des domaines de confiance à configurer. La sauvegarde est distante et sa restauration doit être testée. Qodana Ultimate est déclaré disponible ; les résultats doivent venir d’analyses réelles. Aucune machine n’est déclarée provisionnée par cette présentation.');
}
// 9 — closing
{
const sl=base('La promesse à retenir',9);sl.addImage({path:icon,x:10.64,y:1.03,w:1.6,h:1.64});
tx(sl,'Une communauté qui se rencontre.\nDes projets qui avancent.\nDes connaissances qui restent.',.68,1.48,11.83,2.36,35,C.ink,true,{valign:'top',paraSpaceAfterPt:10});
rect(sl,.68,4.37,11.94,1.16,C.ink,C.ink);tx(sl,'HAAS — La rencontre par le coup de main.',.99,4.68,11.34,.51,30,C.white,true);
tx(sl,'À vérifier avec des testeurs : se découvrir, s’accorder, apporter une aide réellement utile.',.72,6.07,11.89,.50,18.8,C.muted);
note(sl,'Conclusion — 25 secondes','Le mail nous invite à créer la meilleure expérience communautaire possible. Notre réponse est HAAS : se rencontrer autour d’un petit coup de main volontaire, puis rendre l’apport visible. Pas de droit de dépôt ni de résultat inventé après acceptation. Le pilote vérifiera si deux développeurs peuvent effectuer ce premier échange sans que l’équipe orchestre chaque clic. L’atelier constitue une différence visible pour certaines solutions. Notre prochaine preuve doit venir du produit développé et de testeurs : peuvent-ils découvrir, contribuer et reprendre une solution sans accompagnement constant ? Ne pas annoncer de victoire, traction ou preuve technique non acquise.');
}
(async()=>{
await pptx.writeFile({fileName:path.join(root,'livrables/HAAS_Presentation.pptx')});
fs.writeFileSync(path.join(root,'docs/presentation/NOTES_ORATEUR.md'),'# HAAS — Notes orales\n\nNeuf diapositives ; durée proposée de quatre à cinq minutes. Source de cadrage [M] : mail fourni. Les modalités HAAS sont des choix de conception. Les exemples sont fictifs tant que les preuves du produit livré ne les remplacent pas.\n\n'+notes.map((n,i)=>`## ${i+1}. ${n.title}\n\n${n.text}\n`).join('\n'));
console.log('Generated 9 slides');
})();
