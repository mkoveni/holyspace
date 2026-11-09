-- Media context (Symfony only; the Java schema is untouched). Image bytes live on the file system under
-- MEDIA_STORAGE_PATH; these tables hold metadata and the versioned pointer to each owner's current file.
CREATE TABLE media_files (
    id UUID PRIMARY KEY,
    owner_type VARCHAR(20) NOT NULL CHECK (owner_type IN ('account', 'person', 'household', 'event', 'community')),
    owner_id UUID NOT NULL,
    kind VARCHAR(20) NOT NULL CHECK (kind IN ('profile', 'person', 'family', 'family_banner', 'event', 'event_banner', 'community')),
    path VARCHAR(255) NOT NULL CHECK (path !~ '(^/|\.\.)'),
    mime VARCHAR(20) NOT NULL CHECK (mime IN ('image/webp')),
    width INTEGER NOT NULL CHECK (width BETWEEN 1 AND 8000),
    height INTEGER NOT NULL CHECK (height BETWEEN 1 AND 8000),
    byte_size INTEGER NOT NULL CHECK (byte_size BETWEEN 1 AND 5242880),
    sha256 CHAR(64) NOT NULL CHECK (sha256 ~ '^[0-9a-f]{64}$'),
    created_at TIMESTAMPTZ NOT NULL
);

-- One versioned slot per owner and kind. Removal keeps the row with a null file and a higher version, so a stale
-- request can never bring an old image back.
CREATE TABLE media_slots (
    owner_type VARCHAR(20) NOT NULL CHECK (owner_type IN ('account', 'person', 'household', 'event', 'community')),
    owner_id UUID NOT NULL,
    kind VARCHAR(20) NOT NULL CHECK (kind IN ('profile', 'person', 'family', 'family_banner', 'event', 'event_banner', 'community')),
    file_id UUID UNIQUE REFERENCES media_files(id),
    version BIGINT NOT NULL CHECK (version > 0),
    updated_at TIMESTAMPTZ NOT NULL,
    PRIMARY KEY (owner_type, owner_id, kind)
);

-- Files replaced or removed in a transaction are deleted from disk only after it commits; the row survives a crash
-- between the commit and the delete and is drained by a scheduled task.
CREATE TABLE media_pending_deletions (
    file_id UUID PRIMARY KEY,
    path VARCHAR(255) NOT NULL,
    queued_at TIMESTAMPTZ NOT NULL
);

-- Who changed which picture and when. Metadata only: no bytes, file names or EXIF data.
CREATE TABLE media_audit (
    id UUID PRIMARY KEY,
    owner_type VARCHAR(20) NOT NULL,
    target_id UUID NOT NULL,
    kind VARCHAR(20) NOT NULL,
    action VARCHAR(20) NOT NULL CHECK (action IN ('IMAGE_CHANGED', 'IMAGE_REMOVED')),
    reason VARCHAR(200) NOT NULL,
    actor_id UUID NOT NULL,
    occurred_at TIMESTAMPTZ NOT NULL
);
CREATE INDEX media_audit_target_idx ON media_audit (owner_type, target_id, occurred_at DESC, id);
