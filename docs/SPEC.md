# Spécification fonctionnelle — MVP

Version : 2026-11-24. Source : cahier des charges MVP (plateforme de prise de rendez-vous, Côte d'Ivoire).

## 1. Contexte et objectifs

Plateforme de réservation pour professionnels ivoiriens, avec paiement d'acompte par mobile money et rappels WhatsApp. Cible : prestataires qui gèrent aujourd'hui leurs rendez-vous par WhatsApp ou par téléphone.

Problèmes visés : rendez-vous manqués (no-shows), prise de rendez-vous lente et sujette aux doubles réservations, outils existants sans mobile money ni WhatsApp natifs.

Objectifs du MVP :

1. Un professionnel crée son compte, ses services et ses horaires en moins de 10 minutes.
2. Un client réserve et paie un acompte en moins de 2 minutes, sur smartphone, sans créer de compte.
3. Le professionnel et le client reçoivent confirmation et rappels automatiques sur WhatsApp.
4. Le taux de rendez-vous honorés augmente grâce à l'acompte et aux rappels.

Hypothèses à valider : devise FCFA (XOF), fuseau `Africa/Abidjan` (UTC+0), français uniquement, usage surtout mobile avec connexion variable.

## 2. Périmètre

| Fonctionnalité | V1 | V2 | V3 |
| --- | --- | --- | --- |
| Compte professionnel (téléphone + mot de passe, OTP) | Oui | | |
| Services (nom, durée, prix, acompte) | Oui | | |
| Horaires récurrents et exceptions (congés) | Oui | | |
| Page de réservation publique (lien + QR code) | Oui | | |
| Réservation sans compte client | Oui | | |
| Acompte par mobile money | Oui | | |
| Rappels WhatsApp + SMS de secours | Oui | | |
| Annulation et report par lien dans le message | Oui | | |
| Tableau de bord pro (agenda jour/semaine, liste) | Oui | | |
| Multi-employés, planning d'équipe | | Oui | |
| Synchronisation Google Calendar | | Oui | |
| Codes promo, statistiques avancées | | Oui | |
| Paiement intégral, remboursements automatisés | | Oui | |
| API publique, white-label | | | Oui |
| Abonnements et facturation automatique du SaaS | | | Oui |

Hors périmètre V1 : application mobile native, visioconférence intégrée, autres langues que le français, multi-devises.

## 3. Acteurs et parcours

**Professionnel**

1. S'inscrit avec son numéro, valide un code OTP, choisit son identifiant public (`slug`).
2. Crée ses services (durée, prix, acompte).
3. Définit ses horaires hebdomadaires et ses congés.
4. Renseigne le compte mobile money qui recevra les paiements.
5. Partage son lien ou son QR code.
6. Consulte l'agenda, annule, marque un rendez-vous `completed` ou `no_show`.

**Client (sans compte)**

1. Ouvre le lien du professionnel, choisit un service.
2. Choisit un jour puis un créneau libre.
3. Saisit son nom et son numéro WhatsApp.
4. Paie l'acompte par mobile money (créneau retenu 10 minutes).
5. Reçoit confirmation et rappels ; peut annuler ou reporter via le lien du message.

**Administrateur** : liste les professionnels, suspend un compte, consulte paiements et échecs de notification, traite les remboursements manuels.

## 4. Règles métier

### Calcul des créneaux libres (service + jour)

1. Partir des `availability_rules` du jour de la semaine ; appliquer l'exception du jour si elle existe (fermé ou horaires spéciaux).
2. Découper en créneaux d'un pas fixe (15 ou 30 min, réglable).
3. Retirer les créneaux qui chevauchent un rendez-vous `confirmed` ou `pending_payment` non expiré, en comptant le temps tampon (`buffer_min`) après chaque rendez-vous.
4. Retirer le passé et ce qui viole le délai minimum de réservation (2 h par défaut).
5. Ne proposer un créneau que si la durée du service tient avant la fin de la plage.

### Double réservation

Transaction qui verrouille, revérifie, insère. En cas de conflit : `409`. Voir `docs/DATABASE.md` pour la contrainte d'exclusion.

### Annulation et acompte (valeurs par défaut, paramétrables par le pro)

| Cas | Effet sur l'acompte |
| --- | --- |
| Annulation client plus de 24 h avant | Remboursé (manuel en V1) |
| Annulation client moins de 24 h avant | Conservé par le professionnel |
| Annulation par le professionnel | Remboursé au client |
| Client absent (`no_show`) | Conservé par le professionnel |
| Report client plus de 24 h avant | Acompte reporté sur le nouveau créneau |

## 5. Statuts d'un rendez-vous

`pending_payment` (créneau retenu, expire à `hold_expires_at`) · `confirmed` (acompte reçu) · `cancelled_by_customer` · `cancelled_by_pro` · `completed` · `no_show` · `expired`.

## 6. Tarification proposée (à tester avec les pilotes)

Abonnement mensuel du professionnel, de l'ordre de 5 000 à 10 000 FCFA, payé par mobile money, plus une petite commission sur chaque acompte pour couvrir les frais d'agrégateur et de messages. Facturation automatique : V3.

## 7. Critères d'acceptation du MVP

- [ ] Un professionnel configure son compte et publie sa page en moins de 10 minutes.
- [ ] Un client réserve et paie un acompte en moins de 2 minutes sur smartphone.
- [ ] Deux clients ne peuvent jamais réserver le même créneau (test de concurrence).
- [ ] Un webhook reçu deux fois ne confirme qu'un seul rendez-vous.
- [ ] Un paiement dont le webhook est perdu est réconcilié en moins de 10 minutes.
- [ ] Les rappels J-1 et H-2 partent à l'heure, avec repli SMS en cas d'échec WhatsApp.
- [ ] Un créneau non payé est libéré après 10 minutes.
- [ ] Annulation et report fonctionnent via le lien du message, sans compte.

## 8. Documents liés

`ARCHITECTURE.md` · `DATABASE.md` · `API.md` · `PAYMENTS.md` · `NOTIFICATIONS.md` · `TASKS.md` · `DECISIONS.md`
