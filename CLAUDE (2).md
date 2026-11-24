# CLAUDE.md — Plateforme de prise de rendez-vous (Côte d'Ivoire)

Plateforme de réservation en ligne pour professionnels ivoiriens (salons, cliniques, cabinets, coachs, auto-écoles). Le client réserve sans compte, paie un acompte par mobile money, et reçoit confirmation et rappels sur WhatsApp. Nom commercial : à définir (voir `docs/DECISIONS.md`).

Avant de coder, lire `docs/SPEC.md` (périmètre et règles métier). Ne rien implémenter qui soit marqué V2 ou V3.

## Stack

- **Backend** : Laravel (PHP 8.3), API REST, Sanctum, queues Redis, scheduler Laravel.
- **Base de données** : PostgreSQL recommandé (contrainte d'exclusion anti-chevauchement). MySQL reste possible avec verrou applicatif. Schéma de référence : `database/schema.sql`.
- **Front** : Next.js (TypeScript). Page de réservation publique en rendu serveur ; tableau de bord professionnel en PWA.
- **Infra de départ** : VPS + Docker, Redis, HTTPS.

## Structure du dépôt

```
backend/    Laravel (API, jobs, commandes planifiées)
frontend/   Next.js (public + dashboard pro)
database/   schema.sql (référence, les migrations Laravel font foi)
docs/       SPEC, ARCHITECTURE, DATABASE, API, PAYMENTS, NOTIFICATIONS, TASKS, DECISIONS
```

## Commandes usuelles

```
# backend
composer install
php artisan migrate
php artisan test
php artisan queue:work
php artisan schedule:work

# frontend
npm install
npm run dev
npm run lint && npm test
```

## Conventions

- Code, noms de tables, routes et variables en **anglais** ; textes affichés à l'utilisateur et messages WhatsApp/SMS en **français**.
- Montants : **entiers en FCFA (XOF)**, jamais de décimales ni de flottants.
- Dates : stockées en **UTC**, affichées en `Africa/Abidjan`. Numéros de téléphone au format E.164 (`+225...`).
- API versionnée `/api/v1`, réponses JSON, erreurs `409` (créneau pris), `422` (validation), `429` (limite de débit).
- Ne jamais modifier une migration déjà appliquée : en ajouter une nouvelle.
- Aucun secret dans le dépôt (clés agrégateur, WhatsApp, SMS) : variables d'environnement, `.env.example` à jour.
- Le fournisseur de paiement est caché derrière l'interface `PaymentProvider` ; ne jamais appeler un SDK d'opérateur directement depuis un contrôleur.

## Règles métier à ne jamais casser

1. **Pas de double réservation** : la création d'un rendez-vous se fait dans une transaction qui revérifie la disponibilité. Conflit → `409`.
2. **Créneau retenu 10 minutes** (`pending_payment`, `hold_expires_at`). Passé ce délai : `expired`, créneau libéré.
3. **Webhooks idempotents** : clé `payments.provider_ref` unique ; un même webhook reçu deux fois ne confirme qu'une fois. Vérifier la signature avant tout traitement. Conserver le contenu brut dans `raw_payload`.
4. **Réconciliation** : tâche planifiée toutes les 5 minutes sur les paiements restés `pending`.
5. **Rappels** : créés à la confirmation (J-1 et H-2), annulés ou recréés si le rendez-vous est annulé ou déplacé. WhatsApp d'abord, SMS en secours.
6. **Statuts des rendez-vous** : `pending_payment`, `confirmed`, `cancelled_by_customer`, `cancelled_by_pro`, `completed`, `no_show`, `expired`. Ne pas en inventer d'autres sans mettre à jour `docs/DATABASE.md`.
7. Remboursements **manuels en V1** (marqués dans `payments`) ; l'automatisation est V2.

## Tests obligatoires

Toute modification touchant ces zones demande des tests : calcul des créneaux, création concurrente d'un même créneau, webhook reçu deux fois, expiration des créneaux, réconciliation, repli SMS, annulation par lien.

## Façon de travailler

- Suivre l'ordre des lots dans `docs/TASKS.md` ; cocher les tâches terminées.
- Une décision ouverte (agrégateur, fournisseur WhatsApp, etc.) se note dans `docs/DECISIONS.md`, on ne la tranche pas seul dans le code.
- Si la spec et le code divergent, corriger la spec dans le même commit et le dire dans le message.
- Petits commits, un sujet par commit, messages en français ou en anglais mais cohérents.

## Définition de « terminé »

Tests verts, lint propre, migrations appliquées sur base vide, `.env.example` à jour, spec mise à jour si le comportement a changé, et les critères d'acceptation concernés dans `docs/SPEC.md` vérifiés.
