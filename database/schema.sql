-- Schéma de référence (PostgreSQL 14+). Les migrations Laravel font foi :
-- en cas d'écart, corriger l'un ou l'autre dans le même commit (voir docs/DATABASE.md).
--
-- Conventions : dates en timestamptz (UTC), montants en entiers FCFA (XOF),
-- téléphones au format E.164 (+225...).

CREATE EXTENSION IF NOT EXISTS btree_gist;  -- nécessaire à bookings_no_overlap

-- ---------------------------------------------------------------------------
-- professionals
-- ---------------------------------------------------------------------------
CREATE TABLE professionals (
    id               bigserial PRIMARY KEY,
    phone            varchar(20)  NOT NULL UNIQUE,
    password_hash    varchar(255),
    name             varchar(120) NOT NULL,
    slug             varchar(60)  NOT NULL UNIQUE,
    business_name    varchar(160),
    address          varchar(255),
    timezone         varchar(64)  NOT NULL DEFAULT 'Africa/Abidjan',
    whatsapp_number  varchar(20),
    payout_operator  varchar(20),
    payout_phone     varchar(20),
    status           varchar(20)  NOT NULL DEFAULT 'active',
    created_at       timestamptz  NOT NULL DEFAULT now(),
    CONSTRAINT professionals_phone_e164 CHECK (phone ~ '^\+[1-9][0-9]{7,14}$'),
    CONSTRAINT professionals_slug_format CHECK (slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$'),
    CONSTRAINT professionals_payout_operator_valid
        CHECK (payout_operator IS NULL OR payout_operator IN ('wave', 'orange', 'mtn', 'moov')),
    CONSTRAINT professionals_status_valid CHECK (status IN ('active', 'suspended'))
);

-- ---------------------------------------------------------------------------
-- services
-- ---------------------------------------------------------------------------
CREATE TABLE services (
    id               bigserial PRIMARY KEY,
    professional_id  bigint       NOT NULL REFERENCES professionals (id) ON DELETE CASCADE,
    name             varchar(120) NOT NULL,
    duration_min     integer      NOT NULL,
    price_xof        integer      NOT NULL,
    deposit_xof      integer      NOT NULL,
    buffer_min       integer      NOT NULL DEFAULT 0,
    is_active        boolean      NOT NULL DEFAULT true,
    CONSTRAINT services_duration_positive CHECK (duration_min > 0),
    CONSTRAINT services_price_nonnegative CHECK (price_xof >= 0),
    CONSTRAINT services_deposit_range CHECK (deposit_xof >= 0 AND deposit_xof <= price_xof),
    CONSTRAINT services_buffer_nonnegative CHECK (buffer_min >= 0)
);
CREATE INDEX services_professional_idx ON services (professional_id);

-- ---------------------------------------------------------------------------
-- availability_rules : horaires hebdomadaires (weekday : 0 = lundi ... 6 = dimanche)
-- ---------------------------------------------------------------------------
CREATE TABLE availability_rules (
    id               bigserial PRIMARY KEY,
    professional_id  bigint   NOT NULL REFERENCES professionals (id) ON DELETE CASCADE,
    weekday          smallint NOT NULL,
    start_time       time     NOT NULL,
    end_time         time     NOT NULL,
    CONSTRAINT availability_rules_weekday_range CHECK (weekday BETWEEN 0 AND 6),
    CONSTRAINT availability_rules_time_order CHECK (end_time > start_time)
);
CREATE INDEX availability_rules_professional_weekday_idx
    ON availability_rules (professional_id, weekday);

-- ---------------------------------------------------------------------------
-- availability_exceptions : congés (closed) et horaires spéciaux (custom)
-- Une seule exception par professionnel et par jour.
-- ---------------------------------------------------------------------------
CREATE TABLE availability_exceptions (
    id               bigserial PRIMARY KEY,
    professional_id  bigint      NOT NULL REFERENCES professionals (id) ON DELETE CASCADE,
    date             date        NOT NULL,
    type             varchar(10) NOT NULL,
    start_time       time,
    end_time         time,
    CONSTRAINT availability_exceptions_type_valid CHECK (type IN ('closed', 'custom')),
    CONSTRAINT availability_exceptions_times_valid CHECK (
        (type = 'closed' AND start_time IS NULL AND end_time IS NULL)
        OR (type = 'custom' AND start_time IS NOT NULL AND end_time IS NOT NULL AND end_time > start_time)
    ),
    CONSTRAINT availability_exceptions_day_unique UNIQUE (professional_id, date)
);

-- ---------------------------------------------------------------------------
-- customers
-- ---------------------------------------------------------------------------
CREATE TABLE customers (
    id              bigserial PRIMARY KEY,
    name            varchar(120) NOT NULL,
    phone_whatsapp  varchar(20)  NOT NULL,
    created_at      timestamptz  NOT NULL DEFAULT now(),
    CONSTRAINT customers_phone_e164 CHECK (phone_whatsapp ~ '^\+[1-9][0-9]{7,14}$')
);
CREATE INDEX customers_phone_idx ON customers (phone_whatsapp);

-- ---------------------------------------------------------------------------
-- bookings
-- blocked_until = ends_at + buffer_min du service ; c'est lui qui sert au chevauchement.
-- ---------------------------------------------------------------------------
CREATE TABLE bookings (
    id               bigserial PRIMARY KEY,
    professional_id  bigint       NOT NULL REFERENCES professionals (id),
    service_id       bigint       NOT NULL REFERENCES services (id),
    customer_id      bigint       NOT NULL REFERENCES customers (id),
    starts_at        timestamptz  NOT NULL,
    ends_at          timestamptz  NOT NULL,
    blocked_until    timestamptz  NOT NULL,
    status           varchar(30)  NOT NULL DEFAULT 'pending_payment',
    hold_expires_at  timestamptz,
    cancel_token     varchar(100) NOT NULL UNIQUE,
    source           varchar(30)  NOT NULL DEFAULT 'public_page',
    created_at       timestamptz  NOT NULL DEFAULT now(),
    CONSTRAINT bookings_status_valid CHECK (status IN (
        'pending_payment', 'confirmed', 'cancelled_by_customer',
        'cancelled_by_pro', 'completed', 'no_show', 'expired'
    )),
    CONSTRAINT bookings_time_order CHECK (ends_at > starts_at AND blocked_until >= ends_at),
    CONSTRAINT bookings_hold_required CHECK (status <> 'pending_payment' OR hold_expires_at IS NOT NULL),
    -- Interdit deux rendez-vous actifs qui se chevauchent pour un même professionnel.
    -- Ne connaît pas hold_expires_at : avant d'insérer, la transaction de création passe
    -- en 'expired' les pending_payment périmés du même professionnel (voir docs/DATABASE.md).
    CONSTRAINT bookings_no_overlap EXCLUDE USING gist (
        professional_id WITH =,
        tstzrange(starts_at, blocked_until) WITH &&
    ) WHERE (status IN ('pending_payment', 'confirmed'))
);
CREATE INDEX bookings_professional_starts_idx ON bookings (professional_id, starts_at);
-- Tâche d'expiration des créneaux (chaque minute)
CREATE INDEX bookings_hold_expiry_idx ON bookings (hold_expires_at) WHERE status = 'pending_payment';

-- ---------------------------------------------------------------------------
-- payments
-- provider_ref unique : rend les webhooks idempotents (NULL tant que l'agrégateur
-- n'a pas répondu). Plusieurs paiements possibles par rendez-vous (nouvel essai après échec).
-- ---------------------------------------------------------------------------
CREATE TABLE payments (
    id            bigserial PRIMARY KEY,
    booking_id    bigint       NOT NULL REFERENCES bookings (id),
    amount_xof    integer      NOT NULL,
    operator      varchar(20)  NOT NULL,
    provider      varchar(40)  NOT NULL,
    provider_ref  varchar(120) UNIQUE,
    status        varchar(20)  NOT NULL DEFAULT 'pending',
    raw_payload   jsonb,
    paid_at       timestamptz,
    created_at    timestamptz  NOT NULL DEFAULT now(),
    CONSTRAINT payments_amount_positive CHECK (amount_xof > 0),
    CONSTRAINT payments_operator_valid CHECK (operator IN ('wave', 'orange', 'mtn', 'moov')),
    CONSTRAINT payments_status_valid CHECK (status IN (
        'pending', 'succeeded', 'failed', 'refund_due', 'refunded'
    ))
);
CREATE INDEX payments_booking_idx ON payments (booking_id);
-- Réconciliation (toutes les 5 minutes) et écran administrateur
CREATE INDEX payments_status_idx ON payments (status);

-- ---------------------------------------------------------------------------
-- reminders
-- ---------------------------------------------------------------------------
CREATE TABLE reminders (
    id                   bigserial PRIMARY KEY,
    booking_id           bigint       NOT NULL REFERENCES bookings (id) ON DELETE CASCADE,
    channel              varchar(10)  NOT NULL,
    kind                 varchar(30)  NOT NULL,
    scheduled_at         timestamptz  NOT NULL,
    sent_at              timestamptz,
    status               varchar(20)  NOT NULL DEFAULT 'scheduled',
    provider_message_id  varchar(120),
    error                text,
    CONSTRAINT reminders_channel_valid CHECK (channel IN ('whatsapp', 'sms')),
    CONSTRAINT reminders_kind_valid CHECK (kind IN (
        'confirmation', 'pro_new_booking', 'reminder_d1',
        'reminder_h2', 'cancellation', 'payment_expired'
    )),
    CONSTRAINT reminders_status_valid CHECK (status IN (
        'scheduled', 'sent', 'delivered', 'failed', 'cancelled'
    ))
);
-- Envoi des rappels (chaque minute)
CREATE INDEX reminders_status_scheduled_idx ON reminders (status, scheduled_at);
CREATE INDEX reminders_booking_idx ON reminders (booking_id);
-- Webhook de statut WhatsApp : retrouver le rappel par l'identifiant du message
CREATE INDEX reminders_provider_message_idx ON reminders (provider_message_id)
    WHERE provider_message_id IS NOT NULL;
