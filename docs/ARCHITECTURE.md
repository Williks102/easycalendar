# Architecture technique

Une API Laravel sert deux fronts Next.js. Les tâches lentes (paiement, messages) passent par une file Redis pour que la réservation reste rapide.

```
Next.js (page client SSR, PWA pro)
        │ HTTPS / JSON
        ▼
   API Laravel ──► Base de données (PostgreSQL recommandé)
     │  ▲   │
     │  │   └────► Redis (files, verrous, limitation de débit)
     │  │                    │
     ▼  │ webhook             ▼
 Agrégateur de          Workers + scheduler ──► WhatsApp / SMS
 paiement (mobile money)
```

## Composants

| Composant | Rôle |
| --- | --- |
| Next.js public | Page de réservation rapide sur mobile (rendu serveur) |
| Next.js pro (PWA) | Tableau de bord, installable, utilisable avec connexion faible |
| API Laravel | Règles métier, authentification Sanctum, webhooks |
| Base de données | Source de vérité ; sauvegardes quotidiennes automatiques |
| Redis | Files d'attente, verrous, rate limiting |
| Workers + scheduler | Envoi des messages, réconciliation des paiements, expiration des créneaux |
| Agrégateur | Paiement Wave, Orange Money, MTN, Moov (fournisseur à choisir) |

## Tâches planifiées

| Tâche | Fréquence | Rôle |
| --- | --- | --- |
| Expiration des créneaux | chaque minute | `pending_payment` dont `hold_expires_at` est passé → `expired` |
| Envoi des rappels | chaque minute | Envoie les `reminders` dont `scheduled_at` est passé |
| Réconciliation des paiements | toutes les 5 minutes | Interroge l'agrégateur pour les paiements restés `pending` |
| Repli SMS | chaque minute | Bascule en SMS un message WhatsApp sans statut de livraison après 5 min |

## Sécurité

- HTTPS partout, mots de passe hachés, OTP pour la connexion.
- Webhooks vérifiés par signature ; liste blanche d'IP de l'agrégateur si disponible.
- Jetons d'annulation longs, aléatoires, à usage limité.
- Limitation de débit sur les routes publiques et sur l'OTP (maîtrise des coûts SMS).
- Données personnelles limitées au strict nécessaire ; conformité à vérifier avec la réglementation ivoirienne sur la protection des données.

## Performance et qualité

- Calcul des créneaux : moins de 500 ms pour un mois d'agenda.
- Page de réservation : moins de 300 Ko au premier chargement.
- Tests automatisés sur créneaux, webhooks, concurrence.
- Journaux centralisés ; alerte sur échec de webhook ou file bloquée.
