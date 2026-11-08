-- Optional cover images for events and communities (Symfony only; the Java schema is untouched).
-- Bytes live in one table served by random id; each owning context keeps its own versioned pointer.
CREATE TABLE cover_images (
    id UUID PRIMARY KEY,
    content_type VARCHAR(20) NOT NULL CHECK (content_type IN ('image/jpeg', 'image/png', 'image/webp')),
    width INTEGER NOT NULL CHECK (width BETWEEN 1 AND 8000),
    height INTEGER NOT NULL CHECK (height BETWEEN 1 AND 8000),
    byte_size INTEGER NOT NULL CHECK (byte_size BETWEEN 1 AND 5242880),
    content BYTEA NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE NOT NULL
);
CREATE TABLE event_cover_images (
    event_id UUID PRIMARY KEY REFERENCES event_definitions(id),
    image_id UUID UNIQUE REFERENCES cover_images(id),
    version BIGINT NOT NULL CHECK (version > 0),
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL
);
CREATE TABLE community_cover_images (
    community_id UUID PRIMARY KEY REFERENCES communities(id),
    image_id UUID UNIQUE REFERENCES cover_images(id),
    version BIGINT NOT NULL CHECK (version > 0),
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL
);
