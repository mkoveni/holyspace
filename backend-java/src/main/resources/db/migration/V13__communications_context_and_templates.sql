-- Move reusable delivery state while retaining existing approved messages and IDs.
ALTER TABLE children_email_deliveries RENAME TO communication_email_deliveries;
ALTER TABLE children_delivery_events RENAME TO communication_delivery_events;
ALTER TABLE communication_email_deliveries DROP CONSTRAINT IF EXISTS children_email_deliveries_communication_id_fkey;
ALTER TABLE communication_email_deliveries DROP CONSTRAINT IF EXISTS children_email_deliveries_verification_id_fkey;
ALTER TABLE communication_email_deliveries ALTER COLUMN verification_id DROP NOT NULL;
ALTER TABLE communication_email_deliveries ALTER COLUMN contact_id DROP NOT NULL;
ALTER TABLE communication_email_deliveries ALTER COLUMN body TYPE TEXT;
ALTER TABLE communication_email_deliveries ADD COLUMN content_type VARCHAR(30) NOT NULL DEFAULT 'text/plain';
ALTER TABLE communication_email_deliveries ADD COLUMN template_key VARCHAR(100);
ALTER TABLE communication_email_deliveries ADD COLUMN template_version BIGINT;
CREATE TABLE communication_delivery_sources (
 delivery_id UUID PRIMARY KEY REFERENCES communication_email_deliveries(id),
 source_context VARCHAR(100) NOT NULL
);
INSERT INTO communication_delivery_sources(delivery_id,source_context)
 SELECT id,'children-ministry' FROM communication_email_deliveries;
CREATE TABLE communication_audit (
 id UUID PRIMARY KEY,actor_id UUID NOT NULL,action VARCHAR(100) NOT NULL,target_id UUID NOT NULL,occurred_at TIMESTAMPTZ NOT NULL
);
CREATE TABLE communication_templates (
 id UUID PRIMARY KEY,template_key VARCHAR(100) NOT NULL UNIQUE,name VARCHAR(150) NOT NULL,
 subject VARCHAR(200) NOT NULL,html TEXT NOT NULL,variables VARCHAR(2000) NOT NULL,
 status VARCHAR(20) NOT NULL CHECK(status IN ('DRAFT','PUBLISHED','ARCHIVED')),
 version BIGINT NOT NULL CHECK(version>=0),updated_by UUID,updated_at TIMESTAMPTZ NOT NULL,
 published_version BIGINT
);
CREATE TABLE communication_template_revisions (
 template_id UUID NOT NULL REFERENCES communication_templates(id),version BIGINT NOT NULL,
 subject VARCHAR(200) NOT NULL,html TEXT NOT NULL,variables VARCHAR(2000) NOT NULL,
 published_by UUID,published_at TIMESTAMPTZ NOT NULL,PRIMARY KEY(template_id,version)
);
INSERT INTO communication_templates(id,template_key,name,subject,html,variables,status,version,updated_at,published_version) VALUES
 ('10000000-0000-0000-0000-000000000001','children.consent-renewal','Children · Consent renewal','Consent renewal request','<h2>Consent renewal</h2><p>{{message}}</p>','message','PUBLISHED',0,CURRENT_TIMESTAMP,0),
 ('10000000-0000-0000-0000-000000000002','children.session-reminder','Children · Session reminder','Children ministry session reminder','<h2>See you soon</h2><p>{{message}}</p>','message','PUBLISHED',0,CURRENT_TIMESTAMP,0);
INSERT INTO communication_template_revisions(template_id,version,subject,html,variables,published_at)
 SELECT id,version,subject,html,variables,updated_at FROM communication_templates;
INSERT INTO permissions(id,code,description) VALUES
 ('00000000-0000-0000-0000-000000000020','communications.templates.read','Browse communication templates and previews'),
 ('00000000-0000-0000-0000-000000000021','communications.templates.manage','Create, edit, publish and archive communication templates');
INSERT INTO role_permissions(role_id,permission_id)
 SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.name='administrator'
 AND p.code IN ('communications.templates.read','communications.templates.manage');
