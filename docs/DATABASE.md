# Modèle de données

Huit tables. Schéma SQL de référence : `database/schema.sql` (PostgreSQL). Les migrations Laravel font foi ; en cas d'écart, corriger l'un ou l'autre dans le même commit.

Conventions : dates en UTC, montants entiers en FCFA, téléphones en E.164.

| Table | Champs principaux | Relations |
| --- | --- | --- |
| `professionals` | phone (unique), password_hash, name, slug (unique), business_name, address, timezone, whatsapp_number, payout_operator, payout_phone, status (`active` ou `suspended`), created_at | a plusieurs services, rendez-vous |
| `services` | professional_id, name, duration_min, price_xof, deposit_xof, buffer_min, is_active | appartient à un professionnel |
| `availability_rules` | professional_id, weekday (0 = lundi … 6 = dimanche), start_time, end_time | appartient à un professionnel |
| `availability_exceptions` | professional_id, date (unique par professionnel), type (`closed` ou `custom`), start_time, end_time | appartient à un professionnel |
| `customers` | name, phone_whatsapp | a plusieurs rendez-vous |
| `bookings` | professional_id, service_id, customer_id, starts_at, ends_at, blocked_until, status, hold_expires_at, cancel_token (unique), source | a un paiement, des rappels |
| `payments` | booking_id, amount_xof, operator, provider, provider_ref (unique), status, raw_payload, paid_at, created_at | appartient à un rendez-vous |
| `reminders` | booking_id, channel, kind, scheduled_at, sent_at, status, provider_message_id, error | appartient à un rendez-vous |

## Statuts

**bookings.status** : `pending_payment` → `confirmed` → `completed` ou `no_show` ; sorties : `cancelled_by_customer`, `cancelled_by_pro`, `expired`.

**payments.status** : `pending` → `succeeded` ou `failed` ; remboursement : `refund_due` → `refunded`.

**reminders.status** : `scheduled` → `sent` → `delivered` ; sorties : `failed`, `cancelled`.

**reminders.kind** : `confirmation`, `pro_new_booking`, `reminder_d1`, `reminder_h2`, `cancellation`, `payment_expired`.

## Intégrité et concurrence

- Plusieurs paiements possibles par rendez-vous (nouvel essai après un échec). `provider_ref` reste `NULL` tant que l'agrégateur n'a pas répondu.
- `bookings.hold_expires_at` est obligatoire tant que le statut est `pending_payment`.
- Les montants, durées, fuseaux de semaine, formats E.164 et listes de statuts sont contrôlés par des `CHECK` dans `database/schema.sql`.
- `bookings.blocked_until` = `ends_at` + `buffer_min` du service. C'est cette valeur qui sert au chevauchement.
- **PostgreSQL** : la contrainte `bookings_no_overlap` (exclusion `gist` sur `professional_id` et `tstzrange(starts_at, blocked_until)`, pour les statuts `pending_payment` et `confirmed`) interdit physiquement deux rendez-vous actifs qui se chevauchent.
- **MySQL** : pas de contrainte d'exclusion ; verrouiller la ligne du professionnel (`SELECT ... FOR UPDATE`) dans la transaction de création, puis revérifier les chevauchements avant l'insertion.
- **Créneaux expirés** : la contrainte ne connaît pas `hold_expires_at`. Avant d'insérer, la transaction passe en `expired` les rendez-vous `pending_payment` périmés du même professionnel, sinon un créneau abandonné resterait bloqué jusqu'au passage de la tâche planifiée.
- `payments.provider_ref` unique : rend les webhooks idempotents.
- Index `bookings (professional_id, starts_at)` pour le calcul des créneaux ; `reminders (status, scheduled_at)` pour l'envoi des rappels.
