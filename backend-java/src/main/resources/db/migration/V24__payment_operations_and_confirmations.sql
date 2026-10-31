CREATE TABLE payments_webhook_operations (
 id UUID PRIMARY KEY,revision_id UUID NOT NULL REFERENCES payments_configuration_revisions(id),
 mode VARCHAR(4) NOT NULL CHECK(mode IN ('test','live')),kind VARCHAR(10) NOT NULL CHECK(kind IN ('REGISTER','REMOVE')),
 target_id VARCHAR(200),backend_url VARCHAR(1000) NOT NULL,request_hash CHAR(64) NOT NULL,actor_id UUID NOT NULL,
 status VARCHAR(10) NOT NULL CHECK(status IN ('STARTED','UNKNOWN','READY','APPLIED','CLOSED')),
 webhook_id VARCHAR(200),encrypted_secret TEXT,created_at TIMESTAMPTZ NOT NULL
);
CREATE INDEX payments_webhook_operations_pending ON payments_webhook_operations(mode,kind,status);
CREATE TABLE payments_receipts (
 id UUID PRIMARY KEY,order_id UUID NOT NULL REFERENCES payments_orders(id),charge_id UUID NOT NULL UNIQUE REFERENCES payments_charges(id),
 donor_id UUID NOT NULL,contact_id UUID NOT NULL,gift_id UUID NOT NULL,delivery_id UUID NOT NULL UNIQUE,
 approved_by UUID NOT NULL,review_hash CHAR(64) NOT NULL,request_hash CHAR(64) NOT NULL,
 evidence VARCHAR(500) NOT NULL,created_at TIMESTAMPTZ NOT NULL
);
CREATE INDEX payments_receipts_order ON payments_receipts(order_id,created_at);
INSERT INTO permissions(id,code,description) VALUES
 ('00000000-0000-0000-0000-000000000048','payments.receipts.send','Approve verified donor payment confirmations');
INSERT INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p
 WHERE r.name='administrator' AND p.code='payments.receipts.send';
INSERT INTO communication_templates(id,template_key,name,subject,html,variables,status,version,updated_at,published_version) VALUES
 ('10000000-0000-0000-0000-000000000009','payments.receipt','Payments · Donor confirmation','Thank you for your contribution',
 '<h2>Thank you, {{name}}</h2><p>Your contribution of {{amount}} was received on {{date}} and recorded in Giving.</p><p>Provider: {{provider}}</p><p>Reference: {{reference}}</p><p>This is a payment confirmation, not a tax certificate.</p>',
 'name,amount,date,provider,reference','PUBLISHED',0,CURRENT_TIMESTAMP,0);
INSERT INTO communication_template_revisions(template_id,version,subject,html,variables,published_at)
 SELECT id,version,subject,html,variables,updated_at FROM communication_templates WHERE template_key='payments.receipt';
-- [jooq ignore start]
CREATE TRIGGER payments_receipts_immutable BEFORE UPDATE OR DELETE ON payments_receipts FOR EACH ROW EXECUTE FUNCTION giving_reject_mutation();
-- [jooq ignore stop]

UPDATE payments_orders SET review_reason=NULL,version=version+1 WHERE mode='test'
 AND review_reason IN ('Awaiting settlement review.','Refund changed the net settlement. Review the bank match.');
