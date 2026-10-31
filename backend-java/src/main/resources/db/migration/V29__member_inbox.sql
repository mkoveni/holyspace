CREATE TABLE communication_member_preferences (
    account_id UUID NOT NULL REFERENCES user_accounts(id),
    topic VARCHAR(30) NOT NULL CHECK (topic IN ('VOLUNTEERING','EVENTS')),
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    version BIGINT NOT NULL DEFAULT 0 CHECK (version >= 0),
    PRIMARY KEY (account_id, topic)
);
CREATE TABLE communication_member_notifications (
    id UUID PRIMARY KEY,
    account_id UUID NOT NULL REFERENCES user_accounts(id),
    kind VARCHAR(40) NOT NULL CHECK (kind IN ('VOLUNTEER_INVITATION','DUTY_CHANGED','DUTY_ASSIGNED','EVENT_BOOKING_UPDATED')),
    source_key VARCHAR(200) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL,
    read_at TIMESTAMPTZ,
    visible BOOLEAN NOT NULL,
    UNIQUE (account_id, kind, source_key)
);
CREATE INDEX communication_member_inbox_idx ON communication_member_notifications(account_id, created_at DESC, id DESC);
CREATE INDEX communication_member_unread_idx ON communication_member_notifications(account_id) WHERE read_at IS NULL AND visible = TRUE;
