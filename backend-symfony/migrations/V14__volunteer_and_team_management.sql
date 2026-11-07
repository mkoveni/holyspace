CREATE TABLE serving_volunteers (
 id UUID PRIMARY KEY,person_id UUID NOT NULL UNIQUE,account_id UUID UNIQUE,
 status VARCHAR(20) NOT NULL CHECK(status IN ('ACTIVE','PAUSED','ARCHIVED')),
 availability_revision BIGINT NOT NULL DEFAULT 0,version BIGINT NOT NULL DEFAULT 0,
 created_at TIMESTAMPTZ NOT NULL,updated_at TIMESTAMPTZ NOT NULL
);
CREATE TABLE serving_teams (id UUID PRIMARY KEY,name VARCHAR(150) NOT NULL,active BOOLEAN NOT NULL DEFAULT TRUE,version BIGINT NOT NULL DEFAULT 0);
CREATE TABLE serving_memberships (
 id UUID PRIMARY KEY,team_id UUID NOT NULL REFERENCES serving_teams(id),volunteer_id UUID NOT NULL REFERENCES serving_volunteers(id),
 valid_from TIMESTAMPTZ NOT NULL,valid_until TIMESTAMPTZ NOT NULL,revoked_at TIMESTAMPTZ,CHECK(valid_until>valid_from)
);
CREATE INDEX serving_membership_scope_idx ON serving_memberships(team_id,volunteer_id,valid_from,valid_until);
CREATE TABLE serving_roles (
 id UUID PRIMARY KEY,team_id UUID NOT NULL REFERENCES serving_teams(id),name VARCHAR(100) NOT NULL,
 children_role VARCHAR(20) NOT NULL CHECK(children_role IN ('NONE','LEADER','ASSISTANT')),active BOOLEAN NOT NULL DEFAULT TRUE,
 UNIQUE(team_id,name)
);
CREATE TABLE serving_credential_types (id UUID PRIMARY KEY,code VARCHAR(80) NOT NULL UNIQUE,name VARCHAR(150) NOT NULL);
CREATE TABLE serving_role_requirements (role_id UUID NOT NULL REFERENCES serving_roles(id),type_id UUID NOT NULL REFERENCES serving_credential_types(id),PRIMARY KEY(role_id,type_id));
CREATE TABLE serving_role_approvals (
 id UUID PRIMARY KEY,role_id UUID NOT NULL REFERENCES serving_roles(id),volunteer_id UUID NOT NULL REFERENCES serving_volunteers(id),
 valid_from TIMESTAMPTZ NOT NULL,valid_until TIMESTAMPTZ NOT NULL,revoked_at TIMESTAMPTZ,CHECK(valid_until>valid_from)
);
CREATE INDEX serving_approval_scope_idx ON serving_role_approvals(role_id,volunteer_id,valid_from,valid_until);
CREATE TABLE serving_credentials (
 id UUID PRIMARY KEY,volunteer_id UUID NOT NULL REFERENCES serving_volunteers(id),type_id UUID NOT NULL REFERENCES serving_credential_types(id),
 valid_from TIMESTAMPTZ NOT NULL,valid_until TIMESTAMPTZ NOT NULL,evidence VARCHAR(500) NOT NULL,verified_by UUID NOT NULL,
 verified_at TIMESTAMPTZ NOT NULL,revoked_at TIMESTAMPTZ,CHECK(valid_until>valid_from)
);
CREATE INDEX serving_credential_coverage_idx ON serving_credentials(volunteer_id,type_id,valid_from,valid_until);
CREATE TABLE serving_availability_rules (
 id UUID PRIMARY KEY,volunteer_id UUID NOT NULL REFERENCES serving_volunteers(id),revision BIGINT NOT NULL,
 day_of_week INTEGER NOT NULL CHECK(day_of_week BETWEEN 1 AND 7),starts_at TIME NOT NULL,ends_at TIME NOT NULL,
 time_zone VARCHAR(80) NOT NULL,valid_from DATE NOT NULL,valid_until DATE NOT NULL,
 CHECK(ends_at<>starts_at),CHECK(valid_until>valid_from)
);
CREATE INDEX serving_availability_revision_idx ON serving_availability_rules(volunteer_id,revision);
CREATE TABLE serving_availability_exceptions (
 id UUID PRIMARY KEY,volunteer_id UUID NOT NULL REFERENCES serving_volunteers(id),revision BIGINT NOT NULL,
 starts_at TIMESTAMPTZ NOT NULL,ends_at TIMESTAMPTZ NOT NULL,available BOOLEAN NOT NULL,CHECK(ends_at>starts_at)
);
CREATE INDEX serving_exception_revision_idx ON serving_availability_exceptions(volunteer_id,revision,starts_at,ends_at);
CREATE TABLE serving_shifts (
 id UUID PRIMARY KEY,request_id UUID NOT NULL UNIQUE,request_hash CHAR(64) NOT NULL,team_id UUID NOT NULL REFERENCES serving_teams(id),role_id UUID NOT NULL REFERENCES serving_roles(id),
 source_kind VARCHAR(40) NOT NULL,source_id UUID,source_revision VARCHAR(64),starts_at TIMESTAMPTZ NOT NULL,ends_at TIMESTAMPTZ NOT NULL,
 positions INTEGER NOT NULL CHECK(positions BETWEEN 1 AND 100),status VARCHAR(20) NOT NULL CHECK(status IN ('OPEN','CANCELLED')),
 version BIGINT NOT NULL DEFAULT 0,schedule_revision BIGINT NOT NULL DEFAULT 0,CHECK(ends_at>starts_at),CHECK((source_kind='STANDALONE' AND source_id IS NULL) OR (source_kind<>'STANDALONE' AND source_id IS NOT NULL))
);
CREATE INDEX serving_shift_range_idx ON serving_shifts(starts_at,ends_at,team_id);
CREATE TABLE serving_assignments (
 id UUID PRIMARY KEY,request_id UUID NOT NULL UNIQUE,request_hash CHAR(64) NOT NULL,shift_id UUID NOT NULL REFERENCES serving_shifts(id),
 volunteer_id UUID NOT NULL REFERENCES serving_volunteers(id),status VARCHAR(20) NOT NULL CHECK(status IN ('PROPOSED','CONFIRMED','CANCELLED')),
 version BIGINT NOT NULL DEFAULT 0,created_by UUID NOT NULL,created_at TIMESTAMPTZ NOT NULL
);
CREATE INDEX serving_assignment_volunteer_idx ON serving_assignments(volunteer_id,status,shift_id);
CREATE UNIQUE INDEX serving_assignment_active_unique ON serving_assignments(shift_id,volunteer_id) WHERE status<>'CANCELLED';
CREATE TABLE serving_invitations (
 id UUID PRIMARY KEY,assignment_id UUID NOT NULL REFERENCES serving_assignments(id),assignment_version BIGINT NOT NULL,shift_version BIGINT NOT NULL,
 expires_at TIMESTAMPTZ NOT NULL,status VARCHAR(20) NOT NULL CHECK(status IN ('PENDING','ACCEPTED','DECLINED','EXPIRED','WITHDRAWN')),
 version BIGINT NOT NULL DEFAULT 0,responded_at TIMESTAMPTZ
);
CREATE UNIQUE INDEX serving_invitation_pending_unique ON serving_invitations(assignment_id) WHERE status='PENDING';
CREATE INDEX serving_invitation_expiry_idx ON serving_invitations(status,expires_at);
CREATE TABLE serving_assignment_events (id UUID PRIMARY KEY,assignment_id UUID NOT NULL REFERENCES serving_assignments(id),actor_id UUID NOT NULL,action VARCHAR(60) NOT NULL,reason VARCHAR(500) NOT NULL,occurred_at TIMESTAMPTZ NOT NULL);
CREATE TABLE serving_audit (id UUID PRIMARY KEY,actor_id UUID NOT NULL,action VARCHAR(100) NOT NULL,target_id UUID NOT NULL,reason VARCHAR(500) NOT NULL DEFAULT '',occurred_at TIMESTAMPTZ NOT NULL);
CREATE TABLE serving_reminders (id UUID PRIMARY KEY,assignment_id UUID NOT NULL REFERENCES serving_assignments(id),assignment_version BIGINT NOT NULL,shift_version BIGINT NOT NULL,contact_id UUID NOT NULL,review_hash CHAR(64) NOT NULL,verification_evidence VARCHAR(500) NOT NULL,created_by UUID NOT NULL,created_at TIMESTAMPTZ NOT NULL,delivery_id UUID NOT NULL UNIQUE);
CREATE TABLE children_volunteer_staff_links (session_id UUID NOT NULL REFERENCES children_sessions(id),worker_id UUID NOT NULL REFERENCES children_workers(person_id),assignment_id UUID NOT NULL,role VARCHAR(20) NOT NULL,PRIMARY KEY(session_id,worker_id));
INSERT INTO permissions(id,code,description) VALUES
 ('00000000-0000-0000-0000-000000000022','volunteering.read','Browse volunteers, teams and schedules'),
 ('00000000-0000-0000-0000-000000000023','volunteering.manage','Manage volunteers, teams, availability and rosters'),
 ('00000000-0000-0000-0000-000000000024','volunteering.credentials.manage','Verify and revoke volunteer credentials'),
 ('00000000-0000-0000-0000-000000000025','volunteering.serve','View and respond to your own volunteer invitations'),
 ('00000000-0000-0000-0000-000000000026','volunteering.communications.send','Approve reviewed volunteer reminders');
INSERT INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.name='administrator' AND p.code IN ('volunteering.read','volunteering.manage','volunteering.credentials.manage','volunteering.serve','volunteering.communications.send');
INSERT INTO communication_templates(id,template_key,name,subject,html,variables,status,version,updated_at,published_version) VALUES
 ('10000000-0000-0000-0000-000000000003','volunteering.shift-reminder','Volunteers · Shift reminder','Your {{team}} shift','<h2>Hello {{name}}</h2><p>Your {{role}} shift with {{team}} is {{startsAt}} to {{endsAt}}.</p><p>{{message}}</p>','name,team,role,startsAt,endsAt,message','PUBLISHED',0,CURRENT_TIMESTAMP,0);
INSERT INTO communication_template_revisions(template_id,version,subject,html,variables,published_at) SELECT id,version,subject,html,variables,updated_at FROM communication_templates WHERE template_key='volunteering.shift-reminder';
