# API REST

Base : `/api/v1`. Réponses JSON. Authentification par jeton (Laravel Sanctum) côté professionnel. Routes publiques limitées en débit.

| Méthode et route | Rôle | Accès |
| --- | --- | --- |
| `POST /auth/otp/request` | Envoie un code OTP par SMS ou WhatsApp | Public |
| `POST /auth/otp/verify` | Vérifie le code, renvoie le jeton ; crée le compte au premier passage | Public |
| `POST /auth/logout` | Révoque le jeton courant | Pro |
| `GET /public/{slug}` | Profil du pro et services actifs | Public |
| `GET /public/{slug}/slots?service_id=&date=` | Créneaux libres d'un jour | Public |
| `POST /public/{slug}/bookings` | Crée un rendez-vous `pending_payment` et lance le paiement | Public |
| `GET /public/bookings/{cancel_token}` | Consulte un rendez-vous | Public (jeton) |
| `POST /public/bookings/{cancel_token}/cancel` | Annule selon la politique | Public (jeton) |
| `POST /public/bookings/{cancel_token}/reschedule` | Reporte sur un nouveau créneau (acompte reporté) | Public (jeton) |
| `GET /me`, `PATCH /me` | Profil, slug, compte de paiement | Pro |
| `GET/POST/PATCH/DELETE /services` | Gère les services | Pro |
| `GET/PUT /availability` | Horaires hebdomadaires (`PUT` remplace tout) ; `GET` renvoie aussi les exceptions | Pro |
| `POST /availability/exceptions`, `DELETE /availability/exceptions/{id}` | Congés et horaires spéciaux (une exception par jour : un nouveau `POST` sur la même date remplace l'ancienne) | Pro |
| `GET /bookings?from=&to=&status=` | Agenda et liste | Pro |
| `PATCH /bookings/{id}` | Annule, marque `completed` ou `no_show` | Pro |
| `POST /webhooks/payments/{provider}` | Notification de paiement (signature vérifiée) | Agrégateur |
| `POST /webhooks/whatsapp` | Statuts de livraison des messages | WhatsApp |

Les routes d'administration (liste des professionnels, suspension, remboursements manuels) sont à définir dans le lot 3.

## Exemple : création d'un rendez-vous

```json
POST /api/v1/public/salon-ama/bookings
{
  "service_id": 12,
  "starts_at": "2026-12-02T09:30:00Z",
  "customer": { "name": "Koné Awa", "phone": "+2250700000000" },
  "operator": "wave"
}

201 Created
{
  "booking_id": 481,
  "status": "pending_payment",
  "hold_expires_at": "2026-12-02T08:41:00Z",
  "payment": { "amount_xof": 3000, "checkout_url": "https://..." }
}
```

## Erreurs

| Code | Cas |
| --- | --- |
| `409` | Le créneau vient d'être pris |
| `422` | Données invalides |
| `429` | Trop de requêtes |

## Authentification (lot 1)

- `POST /auth/otp/request` `{ "phone": "+225..." }` → `202`. Code à 6 chiffres, valable 10 minutes, à usage unique, bloqué après 5 essais faux. Limites : 3 demandes par 10 minutes et par numéro, 10 par IP (`429`).
- `POST /auth/otp/verify` `{ "phone", "code" }` → `200` avec `{ token, professional }` si le compte existe. Sans compte, `name` et `slug` sont obligatoires (`business_name` et `password` facultatifs) : le compte est créé et la réponse est `201`. Code faux ou expiré → `422` sur `code`.
- Un compte `suspended` reçoit `403` partout.
- L'envoi du code passe par l'interface `OtpSender` ; tant que le fournisseur n'est pas choisi (décision 5), le code est écrit dans les journaux.
- `DELETE /services/{id}` renvoie `409` si le service a déjà des rendez-vous : le désactiver (`is_active`) à la place.

## Règles

- Les créneaux sont renvoyés en UTC avec le fuseau du professionnel ; l'affichage se fait côté front.
- `POST /public/{slug}/bookings` est atomique : vérification de disponibilité, insertion, création du paiement.
- Les webhooks répondent `200` même pour un doublon déjà traité (idempotence), `400` si la signature est invalide.
