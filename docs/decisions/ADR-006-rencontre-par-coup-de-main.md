# ADR-006 — Rencontrer les développeurs par un coup de main

Date : 1er octobre 2026. Statut : décision de conception retenue à la demande « et mettez a jour le zip ». Implémentation à réaliser.

## Contexte
Le mail CADEV met en avant rencontres, échanges, projets, collaboration et utilité. F16/F17 couvrent la découverte, mais l’absence de demande ouverte empêchait une proposition spontanée d’aide. La décision utilisateur suivante retient un coup de main accepté sur un besoin concret.

## Décision
Ajouter F18 : projet volontairement ouvert ; offre bornée d’un tiers ; acceptation/refus/retrait ; création ou rattachement explicite à un fil public existant ; vue des progrès documentés. Deux entrées « Faire avancer mon projet » et « Donner un coup de main ». Suggestions = filtres explicables sur des données déclarées, pas un moteur IA.

## Portée et précédence
COUPS_DE_MAIN.md et COUPS_DE_MAIN_API.md font foi sur le nouveau parcours. Ils remplacent UNIQUEMENT l’ancienne impasse « aucune demande ouverte, aucune aide spontanée ». F16 garde la propriété des demandes et projets : un tiers ne rattache jamais librement une demande. Le propriétaire autorise lui-même la création/le rattachement en acceptant. Pas de nouveau fil autonome, de chat privé ni d’accès implicite au dépôt.

ADR-001 (backend-first), ADR-004 (un VPS Systalink + Vercel) et ADR-005 (communauté d’abord) restent applicables. Le mail source n’est ni réécrit ni présenté comme imposant F18.

## Sécurité et réalisation
Consentement explicite des deux acteurs avant projection publique, offre pending privée, droits/retrait/concurrence testés. Le compte annuaire est optionnel pour proposer de l’aide. Une offre acceptée ne prouve ni contribution terminée ni sécurité du code. Préserver les historiques et preuves existants ; ne pas appliquer de migration destructive.

## Coût et limites
16 lots complémentaires, charge à réestimer ; 22 AC et 3 familles d’écrans. Aucun nombre de lots n’est une estimation en heures. Pas de nouvel abonnement, de chatbot, de calendrier ou de serveur exigé par cette conception. Conserver les quotas, tests et limites d’exploitation existants.
