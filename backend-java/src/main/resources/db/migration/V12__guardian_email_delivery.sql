CREATE TABLE children_verified_emails (
 id UUID PRIMARY KEY,guardian_id UUID NOT NULL REFERENCES people_persons(id),contact_id UUID NOT NULL,
 email VARCHAR(254) NOT NULL,verified_by UUID NOT NULL,verified_at TIMESTAMPTZ NOT NULL,valid_until TIMESTAMPTZ NOT NULL,
 evidence VARCHAR(500) NOT NULL,revoked_at TIMESTAMPTZ,CHECK(valid_until>verified_at)
);
CREATE INDEX children_verified_email_contact_idx ON children_verified_emails(guardian_id,contact_id,valid_until);
CREATE TABLE children_email_deliveries (
 id UUID PRIMARY KEY,communication_id UUID NOT NULL UNIQUE REFERENCES children_guardian_communications(id),
 verification_id UUID NOT NULL REFERENCES children_verified_emails(id),contact_id UUID NOT NULL,recipient VARCHAR(254) NOT NULL,
 subject VARCHAR(200) NOT NULL,body VARCHAR(3000) NOT NULL,review_hash CHAR(64) NOT NULL,
 approved_by UUID NOT NULL,approved_at TIMESTAMPTZ NOT NULL,scheduled_at TIMESTAMPTZ NOT NULL,
 status VARCHAR(30) NOT NULL CHECK(status IN ('QUEUED','SCHEDULED','SENDING','SENT','DELIVERED','FAILED','UNKNOWN','CANCELLED','BLOCKED')),
 attempts INTEGER NOT NULL DEFAULT 0 CHECK(attempts>=0),message_id VARCHAR(100) NOT NULL UNIQUE,
 attempt_id UUID,started_at TIMESTAMPTZ,finished_at TIMESTAMPTZ,error_code VARCHAR(80) NOT NULL DEFAULT '',
 captured BOOLEAN NOT NULL DEFAULT FALSE,version BIGINT NOT NULL DEFAULT 0 CHECK(version>=0)
);
CREATE INDEX children_delivery_due_idx ON children_email_deliveries(status,scheduled_at,id);
CREATE TABLE children_delivery_events (
 id UUID PRIMARY KEY,delivery_id UUID NOT NULL REFERENCES children_email_deliveries(id),type VARCHAR(30) NOT NULL,
 occurred_at TIMESTAMPTZ NOT NULL,actor_id UUID,event_key VARCHAR(200) UNIQUE,event_hash CHAR(64),reference VARCHAR(500) NOT NULL DEFAULT ''
);
CREATE INDEX children_delivery_event_idx ON children_delivery_events(delivery_id,occurred_at,id);
INSERT INTO permissions(id,code,description) VALUES
 ('00000000-0000-0000-0000-000000000018','children.communications.send','Review, schedule and send guardian email'),
 ('00000000-0000-0000-0000-000000000019','children.communications.receipts','Submit authenticated provider delivery receipts');
INSERT INTO role_permissions(role_id,permission_id)
 SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.name='administrator'
 AND p.code IN ('children.communications.send','children.communications.receipts');
