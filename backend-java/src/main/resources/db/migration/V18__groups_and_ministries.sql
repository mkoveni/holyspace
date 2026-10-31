CREATE TABLE communities (
 id UUID PRIMARY KEY,kind VARCHAR(10) NOT NULL CHECK(kind IN ('MINISTRY','GROUP')),name VARCHAR(150) NOT NULL,
 description VARCHAR(5000) NOT NULL,parent_id UUID,campus VARCHAR(150) NOT NULL DEFAULT '',
 parent_kind VARCHAR(10) NOT NULL DEFAULT 'MINISTRY' CHECK(parent_kind='MINISTRY'),
 active BOOLEAN NOT NULL DEFAULT TRUE,version BIGINT NOT NULL DEFAULT 0,UNIQUE(id,kind),
 CHECK(parent_id IS NULL OR kind='GROUP'),FOREIGN KEY(parent_id,parent_kind) REFERENCES communities(id,kind)
);
CREATE INDEX community_directory_idx ON communities(kind,active,name,id);
CREATE INDEX community_parent_idx ON communities(parent_id);
CREATE TABLE community_memberships (
 id UUID PRIMARY KEY,community_id UUID NOT NULL REFERENCES communities(id),person_id UUID NOT NULL,
 started_at TIMESTAMPTZ NOT NULL,ended_at TIMESTAMPTZ,version BIGINT NOT NULL DEFAULT 0,
 CHECK(ended_at IS NULL OR ended_at>=started_at),UNIQUE(id,community_id)
);
CREATE INDEX community_member_person_idx ON community_memberships(person_id,community_id);
CREATE TABLE community_leadership (
 id UUID PRIMARY KEY,membership_id UUID NOT NULL,community_id UUID NOT NULL,account_id UUID,
 role VARCHAR(20) NOT NULL CHECK(role IN ('LEADER','CO_LEADER')),started_at TIMESTAMPTZ NOT NULL,ended_at TIMESTAMPTZ,
 version BIGINT NOT NULL DEFAULT 0,CHECK(ended_at IS NULL OR ended_at>=started_at),
 FOREIGN KEY(membership_id,community_id) REFERENCES community_memberships(id,community_id)
);
CREATE INDEX community_leader_account_idx ON community_leadership(account_id,community_id);
CREATE TABLE community_meetings (
 id UUID PRIMARY KEY,community_id UUID NOT NULL REFERENCES communities(id),occurrence_id UUID NOT NULL UNIQUE,
 scheduled_at TIMESTAMPTZ NOT NULL,status VARCHAR(15) NOT NULL CHECK(status IN ('OPEN','CANCELLED','CLOSED')),roster_frozen BOOLEAN NOT NULL DEFAULT FALSE,
 version BIGINT NOT NULL DEFAULT 0
);
CREATE INDEX community_meeting_parent_idx ON community_meetings(community_id,scheduled_at DESC,id);
CREATE TABLE community_roster (
 id UUID PRIMARY KEY,meeting_id UUID NOT NULL REFERENCES community_meetings(id),person_id UUID NOT NULL,
 membership_id UUID REFERENCES community_memberships(id),guest BOOLEAN NOT NULL DEFAULT FALSE,
 status VARCHAR(15) NOT NULL CHECK(status IN ('UNRECORDED','PRESENT','ABSENT','EXCUSED')),
 version BIGINT NOT NULL DEFAULT 0,UNIQUE(meeting_id,person_id)
);
CREATE TABLE community_history (
 id UUID PRIMARY KEY,community_id UUID NOT NULL REFERENCES communities(id),target_id UUID NOT NULL,
 action VARCHAR(50) NOT NULL,reason VARCHAR(2000) NOT NULL,actor_id UUID NOT NULL,occurred_at TIMESTAMPTZ NOT NULL
);
CREATE INDEX community_history_idx ON community_history(community_id,occurred_at DESC,id);
CREATE TABLE community_requests (
 id UUID PRIMARY KEY,request_hash CHAR(64) NOT NULL,target_id UUID NOT NULL
);
CREATE TABLE community_followups (
 id UUID PRIMARY KEY,community_id UUID NOT NULL REFERENCES communities(id),person_id UUID NOT NULL,
 note VARCHAR(2000) NOT NULL,due_on DATE NOT NULL,status VARCHAR(10) NOT NULL CHECK(status IN ('OPEN','DONE')),
 version BIGINT NOT NULL DEFAULT 0,created_by UUID NOT NULL,created_at TIMESTAMPTZ NOT NULL
);
CREATE INDEX community_followup_idx ON community_followups(community_id,status,due_on,id);
CREATE TABLE community_notices (
 id UUID PRIMARY KEY,community_id UUID NOT NULL REFERENCES communities(id),membership_id UUID NOT NULL REFERENCES community_memberships(id),
 contact_id UUID NOT NULL,request_hash CHAR(64) NOT NULL,review_hash CHAR(64) NOT NULL,
 evidence VARCHAR(500) NOT NULL,created_by UUID NOT NULL,created_at TIMESTAMPTZ NOT NULL,delivery_id UUID NOT NULL UNIQUE
);
CREATE INDEX community_notice_idx ON community_notices(community_id,created_at,id);
INSERT INTO permissions(id,code,description) VALUES
 ('00000000-0000-0000-0000-000000000034','ministries.read','Browse ministries and group directories'),
 ('00000000-0000-0000-0000-000000000035','ministries.manage','Manage ministry and group participation and leadership'),
 ('00000000-0000-0000-0000-000000000036','ministries.lead','Access current appointed groups'),
 ('00000000-0000-0000-0000-000000000037','ministries.communications.send','Approve reviewed group and ministry notices');
INSERT INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p
 WHERE r.name='administrator' AND p.code IN ('ministries.read','ministries.manage','ministries.lead','ministries.communications.send');
INSERT INTO communication_templates(id,template_key,name,subject,html,variables,status,version,updated_at,published_version) VALUES
 ('10000000-0000-0000-0000-000000000008','communities.notice','Groups and ministries · Member notice','A message from {{community}}',
 '<h2>Hello {{name}}</h2><p>{{community}}</p><p>{{message}}</p>','name,community,message','PUBLISHED',0,CURRENT_TIMESTAMP,0);
INSERT INTO communication_template_revisions(template_id,version,subject,html,variables,published_at)
 SELECT id,version,subject,html,variables,updated_at FROM communication_templates WHERE template_key='communities.notice';

-- [jooq ignore start]
ALTER TABLE community_memberships ADD CONSTRAINT community_membership_period_excl EXCLUDE USING gist (community_id WITH =,person_id WITH =,tstzrange(started_at,ended_at,'[)') WITH &&);
ALTER TABLE community_leadership ADD CONSTRAINT community_leadership_member_excl EXCLUDE USING gist (membership_id WITH =,tstzrange(started_at,ended_at,'[)') WITH &&);
ALTER TABLE community_leadership ADD CONSTRAINT community_leadership_account_excl EXCLUDE USING gist (community_id WITH =,account_id WITH =,tstzrange(started_at,ended_at,'[)') WITH &&);
-- [jooq ignore stop]
