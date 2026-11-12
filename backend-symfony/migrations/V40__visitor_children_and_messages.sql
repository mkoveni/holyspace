-- Visitor self check-in, phase 2: children named on the form wait for Children's Ministry staff, and a visitor
-- who gave an email address gets a welcome and a next-day follow-up through Communications templates.
CREATE TABLE event_visitor_children (
    id UUID PRIMARY KEY,
    registration_id UUID NOT NULL REFERENCES event_visitor_check_ins(registration_id),
    first_name VARCHAR(100) NOT NULL,
    position SMALLINT NOT NULL,
    birth_date DATE NOT NULL,
    handover_status VARCHAR(12) NOT NULL CHECK (handover_status IN ('PENDING', 'HANDED_OVER', 'DISMISSED')),
    handled_by UUID,
    handled_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL
);
CREATE INDEX event_visitor_children_registration_idx ON event_visitor_children(registration_id, position);
CREATE INDEX event_visitor_children_pending_idx ON event_visitor_children(handover_status, created_at) WHERE handover_status = 'PENDING';

-- Who approved automatic messages for an occurrence. Communications asks Events again before every send.
CREATE TABLE event_visit_messaging (
    occurrence_id UUID PRIMARY KEY REFERENCES event_occurrences(id),
    approved_by UUID NOT NULL,
    approved_at TIMESTAMPTZ NOT NULL
);
CREATE TABLE event_visitor_messages (
    registration_id UUID NOT NULL REFERENCES event_visitor_check_ins(registration_id),
    purpose VARCHAR(10) NOT NULL CHECK (purpose IN ('WELCOME', 'FOLLOW_UP')),
    delivery_id UUID NOT NULL UNIQUE,
    review_hash CHAR(64) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL,
    PRIMARY KEY (registration_id, purpose)
);

INSERT INTO communication_templates(id,template_key,name,subject,html,variables,status,version,updated_at,published_version) VALUES
 ('10000000-0000-0000-0000-00000000000a','events.visitor_welcome','Events · Welcome, first-time visitor','Welcome to {{event}}','<h2>Hello {{name}}</h2><p>Thank you for joining us at {{event}}. We are glad you came.</p><p>{{startsAt}} · {{timeZone}} · {{venue}}</p><p>If you have any questions, speak to anyone on our welcome team.</p>','name,event,startsAt,timeZone,venue','PUBLISHED',0,CURRENT_TIMESTAMP,0),
 ('10000000-0000-0000-0000-00000000000b','events.visitor_follow_up','Events · Following up after your visit','Thank you for visiting {{event}}','<h2>Hello {{name}}</h2><p>It was good to have you with us at {{event}} on {{startsAt}} ({{timeZone}}). We would love to see you again.</p><p>{{venue}}</p>','name,event,startsAt,timeZone,venue','PUBLISHED',0,CURRENT_TIMESTAMP,0);
INSERT INTO communication_template_revisions(template_id,version,subject,html,variables,published_at)
 SELECT id,version,subject,html,variables,updated_at FROM communication_templates WHERE template_key IN ('events.visitor_welcome','events.visitor_follow_up');
