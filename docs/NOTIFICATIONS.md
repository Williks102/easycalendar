# Notifications WhatsApp et SMS

WhatsApp est le canal principal, le SMS sert de secours. Les messages sortants d'une entreprise passent par des modèles (templates) approuvés à l'avance par WhatsApp : **déposer les modèles dès le début du projet**, l'approbation prend du temps.

## Messages

| Clé (`reminders.kind`) | Déclencheur | Destinataire | Contenu |
| --- | --- | --- | --- |
| `confirmation` | Paiement reçu | Client | Service, date, heure, adresse, lien d'annulation |
| `pro_new_booking` | Paiement reçu | Professionnel | Client, service, date, heure |
| `reminder_d1` | 24 h avant | Client | Rappel + lien d'annulation |
| `reminder_h2` | 2 h avant | Client | Rappel court + adresse |
| `cancellation` | Annulation par une des parties | L'autre partie | Créneau libéré, politique de remboursement |
| `payment_expired` | Délai de 10 minutes dépassé | Client | Lien pour reprendre la réservation |

## Planification

À la confirmation, le serveur crée les lignes `reminders` avec leur `scheduled_at`. Une tâche planifiée (chaque minute) envoie celles dont l'heure est passée. Rendez-vous annulé ou déplacé : ses rappels sont annulés (`cancelled`) ou recréés.

## Repli SMS

Sans statut de livraison WhatsApp après 5 minutes, ou en cas d'échec, le message part par SMS. Le coût de chaque canal est à intégrer au calcul de la commission.

## Consentement

Le formulaire de réservation indique que le client accepte de recevoir des messages liés à son rendez-vous.

## Brouillons de modèles WhatsApp (français)

Variables entre doubles accolades. À adapter au format exigé par le fournisseur retenu et à soumettre en catégorie « utilitaire » (messages liés à une transaction ou à un rendez-vous).

**`booking_confirmation`**
> Bonjour {{1}}, votre rendez-vous chez {{2}} est confirmé : {{3}}, le {{4}} à {{5}}. Adresse : {{6}}. Pour annuler ou reporter : {{7}}

**`reminder_d1`**
> Rappel : vous avez rendez-vous demain chez {{1}} à {{2}} ({{3}}). Pour annuler ou reporter : {{4}}

**`reminder_h2`**
> Rappel : votre rendez-vous chez {{1}} est dans 2 heures, à {{2}}. Adresse : {{3}}

**`pro_new_booking`**
> Nouveau rendez-vous : {{1}}, {{2}}, le {{3}} à {{4}}. Acompte payé : {{5}} FCFA.

**`booking_cancelled`**
> Le rendez-vous du {{1}} à {{2}} chez {{3}} a été annulé. {{4}}

**`payment_expired`**
> Votre créneau chez {{1}} n'a pas été confirmé faute de paiement. Pour réserver à nouveau : {{2}}

## Tests à écrire

- Création des rappels à la confirmation ; annulation des rappels à l'annulation du rendez-vous.
- Envoi à l'heure ; pas de double envoi si la tâche tourne deux fois.
- Repli SMS après 5 minutes sans statut de livraison.
- Webhook de statut WhatsApp : mise à jour de `delivered` ou `failed`.
