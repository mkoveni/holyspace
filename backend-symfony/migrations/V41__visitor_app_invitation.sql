-- Visitor self check-in, phase 3: staff make a visitor a member and may email an invitation to the church app.
ALTER TABLE event_visitor_messages DROP CONSTRAINT event_visitor_messages_purpose_check;
ALTER TABLE event_visitor_messages ADD CONSTRAINT event_visitor_messages_purpose_check CHECK (purpose IN ('WELCOME', 'FOLLOW_UP', 'APP_INVITE'));

INSERT INTO communication_templates(id,template_key,name,subject,html,variables,status,version,updated_at,published_version) VALUES
 ('10000000-0000-0000-0000-00000000000c','events.visitor_app_invite','Events · Invitation to the church app','You are invited to the church app','<h2>Hello {{name}}</h2><p>It has been wonderful having you with us at {{event}}, and we are glad to welcome you as a member.</p><p>Our app keeps you in touch with events, groups and giving. Ask the welcome team to link your account and they will set you up.</p>','name,event','PUBLISHED',0,CURRENT_TIMESTAMP,0);
INSERT INTO communication_template_revisions(template_id,version,subject,html,variables,published_at)
 SELECT id,version,subject,html,variables,updated_at FROM communication_templates WHERE template_key = 'events.visitor_app_invite';
