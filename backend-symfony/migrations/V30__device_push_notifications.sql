CREATE TABLE communication_push_devices (
 id UUID PRIMARY KEY, account_id UUID NOT NULL REFERENCES user_accounts(id), session_id UUID NOT NULL,
 secret_hash CHAR(64) NOT NULL, token_hash CHAR(64) NOT NULL, encrypted_token VARCHAR(1000) NOT NULL,
 project_id UUID NOT NULL, platform VARCHAR(10) NOT NULL CHECK(platform IN ('android','ios')),
 status VARCHAR(12) NOT NULL CHECK(status IN ('ACTIVE','REVOKED','INVALID')),
 revision BIGINT NOT NULL CHECK(revision >= 0), expires_at TIMESTAMPTZ NOT NULL, updated_at TIMESTAMPTZ NOT NULL
);
CREATE UNIQUE INDEX communication_push_active_token_idx ON communication_push_devices(token_hash) WHERE status = 'ACTIVE';
CREATE INDEX communication_push_owner_idx ON communication_push_devices(account_id,updated_at DESC,id);
CREATE TABLE communication_push_preferences (
 account_id UUID NOT NULL REFERENCES user_accounts(id), topic VARCHAR(30) NOT NULL CHECK(topic IN ('VOLUNTEERING','EVENTS')),
 enabled BOOLEAN NOT NULL DEFAULT FALSE, version BIGINT NOT NULL DEFAULT 0 CHECK(version >= 0), PRIMARY KEY(account_id,topic)
);
CREATE TABLE communication_push_deliveries (
 id UUID PRIMARY KEY, notification_id UUID NOT NULL REFERENCES communication_member_notifications(id),
 device_id UUID NOT NULL REFERENCES communication_push_devices(id), device_revision BIGINT NOT NULL,
 topic VARCHAR(30) NOT NULL, preference_version BIGINT NOT NULL,
 status VARCHAR(24) NOT NULL CHECK(status IN ('QUEUED','SENDING','RETRY','TICKET','CHECKING','PROVIDER_ACCEPTED','FAILED','UNKNOWN','CANCELLED')),
 attempts INTEGER NOT NULL DEFAULT 0, due_at TIMESTAMPTZ NOT NULL, created_at TIMESTAMPTZ NOT NULL,
 claim_id UUID, lease_until TIMESTAMPTZ, ticket_id VARCHAR(200), error_code VARCHAR(50) NOT NULL DEFAULT '',
 UNIQUE(notification_id,device_id)
);
CREATE INDEX communication_push_due_idx ON communication_push_deliveries(status,due_at,id);
CREATE INDEX communication_push_lease_idx ON communication_push_deliveries(lease_until) WHERE status IN ('SENDING','CHECKING');
CREATE INDEX communication_push_device_expiry_idx ON communication_push_devices(expires_at) WHERE status = 'ACTIVE';
