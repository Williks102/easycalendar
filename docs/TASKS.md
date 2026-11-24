# Plan de travail — MVP

Quatre lots, dans cet ordre. Durées estimées pour une petite équipe, à recaler avec le développeur. Cocher au fur et à mesure.

## Lot 1 — Socle (≈ 2 semaines)

- [x] Initialiser le dépôt (backend Laravel, frontend Next.js, Docker, `.env.example`)
- [x] Migrations des 8 tables (voir `docs/DATABASE.md`)
- [x] Inscription professionnelle : téléphone, OTP, mot de passe, `slug` unique
- [x] Authentification Sanctum
- [x] CRUD des services (durée, prix, acompte, tampon)
- [x] Horaires hebdomadaires et exceptions (congés)
- [x] Profil du professionnel et compte de paiement
- [x] CI : tests et lint à chaque commit

## Lot 2 — Réservation (≈ 3 semaines)

- [ ] Service de calcul des créneaux libres (règles de `docs/SPEC.md`, section 4)
- [ ] `GET /public/{slug}` et `GET /public/{slug}/slots`
- [ ] Création du rendez-vous `pending_payment` avec transaction et contrôle de chevauchement
- [ ] Page de réservation publique (Next.js, rendu serveur, mobile d'abord)
- [ ] Tableau de bord pro : agenda jour/semaine, liste, annulation, `completed`, `no_show`
- [ ] Génération du lien public et du QR code
- [ ] Tests : calcul des créneaux, concurrence sur un même créneau

## Lot 3 — Paiement (≈ 2 semaines)

- [ ] Interface `PaymentProvider` et implémentation de l'agrégateur retenu
- [ ] Création du paiement et du lien ou de la demande de validation
- [ ] Webhook : signature, idempotence, confirmation du rendez-vous
- [ ] Tâche d'expiration des créneaux (10 minutes)
- [ ] Tâche de réconciliation (toutes les 5 minutes)
- [ ] Gestion du paiement tardif et du marquage `refund_due`
- [ ] Écran administrateur minimal : paiements, remboursements manuels
- [ ] Tests : doublon de webhook, webhook perdu, paiement tardif

## Lot 4 — Notifications (≈ 2 semaines)

- [ ] Modèles WhatsApp soumis et approuvés (à lancer dès le lot 1)
- [ ] Création des `reminders` à la confirmation (J-1 et H-2)
- [ ] Envoi planifié chaque minute, sans double envoi
- [ ] Webhook de statut WhatsApp
- [ ] Repli SMS après 5 minutes sans livraison
- [ ] Annulation et report par lien (pages publiques sans compte)
- [ ] Messages à l'annulation et à l'expiration du paiement
- [ ] Tests : planification, annulation des rappels, repli SMS

## Pilote (2 à 4 semaines)

- [ ] Recruter 5 à 10 professionnels d'un seul secteur
- [ ] Mesurer : temps de configuration, temps de réservation, taux de no-show, paiements en échec
- [ ] Vérifier tous les critères d'acceptation de `docs/SPEC.md`, section 7
- [ ] Décider du tarif (abonnement et commission) à partir des retours
