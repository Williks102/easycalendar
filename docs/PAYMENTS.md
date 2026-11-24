# Paiement mobile money

Un seul agrégateur de paiement gère les opérateurs (Wave, Orange Money, MTN, Moov). Le choix reste à trancher (`DECISIONS.md`). Le code isole le fournisseur derrière une interface pour pouvoir en changer.

## Interface à implémenter

```php
interface PaymentProvider
{
    // Crée un paiement et renvoie la référence fournisseur + lien ou demande de validation
    public function createCharge(Booking $booking, string $operator, string $phone): ChargeResult;

    // Interroge l'état d'un paiement (utilisé par la réconciliation)
    public function getStatus(string $providerRef): PaymentStatus;

    // Vérifie la signature d'un webhook et renvoie un événement normalisé
    public function parseWebhook(Request $request): PaymentEvent;
}
```

Remboursement automatique (`refund`) : V2.

## Flux d'un paiement

1. Le client valide son créneau : le serveur crée le rendez-vous `pending_payment` avec `hold_expires_at` = maintenant + 10 minutes.
2. Le serveur appelle l'agrégateur : lien de paiement, ou demande de validation sur le téléphone du client.
3. Le client valide dans son application ou par code USSD.
4. L'agrégateur appelle le webhook ; le serveur vérifie la signature, retrouve le paiement par `provider_ref`, passe le rendez-vous en `confirmed`.
5. Les messages de confirmation partent via la file d'attente.

## Règles de fiabilité

- **Idempotence** : `provider_ref` unique ; un webhook reçu deux fois ne confirme qu'une fois.
- **Réconciliation** : toutes les 5 minutes, interroger l'agrégateur pour les paiements restés `pending` (webhook perdu).
- **Expiration** : une tâche libère les créneaux périmés (`expired`).
- **Paiement tardif** : paiement reçu après expiration et créneau encore libre → rendez-vous confirmé ; sinon paiement marqué `refund_due` et signalé à l'administrateur.
- **Journalisation** : contenu brut de chaque webhook dans `payments.raw_payload`.
- Montants en FCFA entiers, jamais de conversion.

## Tests à écrire

- Webhook valide, webhook en double, signature invalide.
- Webhook perdu puis réconciliation.
- Paiement tardif avec créneau libre, puis avec créneau repris.
- Échec de paiement : le rendez-vous reste `pending_payment` jusqu'à expiration, le client peut réessayer.

## Questions ouvertes

- Quel agrégateur et quels frais par transaction ?
- Les fonds passent-ils par le compte de la plateforme (puis reversement au professionnel) ou arrivent-ils directement chez lui ? Cela change la conformité et la commission.
- Un agrément ou une déclaration est-il nécessaire pour encaisser pour le compte de tiers ?
