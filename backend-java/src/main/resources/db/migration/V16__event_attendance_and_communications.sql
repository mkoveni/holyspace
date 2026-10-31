CREATE TABLE event_attendance (
 id UUID PRIMARY KEY,registration_id UUID NOT NULL UNIQUE REFERENCES event_registrations(id),arrived_at TIMESTAMPTZ NOT NULL,
 status VARCHAR(20) NOT NULL CHECK(status IN ('PRESENT','VOIDED')),version BIGINT NOT NULL CHECK(version>=0)
);
CREATE TABLE event_attendance_history (
 id UUID PRIMARY KEY,attendance_id UUID NOT NULL REFERENCES event_attendance(id),actor_id UUID NOT NULL,
 action VARCHAR(30) NOT NULL,reason VARCHAR(1000) NOT NULL,occurred_at TIMESTAMPTZ NOT NULL
);
CREATE INDEX event_attendance_history_idx ON event_attendance_history(attendance_id,occurred_at,id);
CREATE TABLE event_notifications (
 id UUID PRIMARY KEY,registration_id UUID NOT NULL REFERENCES event_registrations(id),registration_version BIGINT NOT NULL,
 schedule_revision BIGINT NOT NULL,event_version BIGINT NOT NULL,request_hash CHAR(64) NOT NULL,purpose VARCHAR(30) NOT NULL CHECK(purpose IN ('CONFIRMATION','REMINDER','CANCELLATION','RESCHEDULED')),
 contact_id UUID NOT NULL,review_hash CHAR(64) NOT NULL,verification_evidence VARCHAR(500) NOT NULL,
 created_by UUID NOT NULL,created_at TIMESTAMPTZ NOT NULL,delivery_id UUID NOT NULL UNIQUE
);
CREATE INDEX event_notification_registration_idx ON event_notifications(registration_id,created_at,id);
INSERT INTO permissions(id,code,description) VALUES
 ('00000000-0000-0000-0000-000000000031','events.attendance.read','Browse and report event attendance'),
 ('00000000-0000-0000-0000-000000000032','events.attendance.manage','Check in attendees and correct attendance'),
 ('00000000-0000-0000-0000-000000000033','events.communications.send','Approve reviewed event communications');
INSERT INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.name='administrator' AND p.code IN ('events.attendance.read','events.attendance.manage','events.communications.send');
INSERT INTO communication_templates(id,template_key,name,subject,html,variables,status,version,updated_at,published_version) VALUES
 ('10000000-0000-0000-0000-000000000004','events.confirmation','Events · Registration confirmed','Registration confirmed: {{event}}','<h2>Hello {{name}}</h2><p>Registration confirmed.</p><p>{{event}} · {{startsAt}} to {{endsAt}} · {{timeZone}}</p><p>{{venue}}</p><p>{{message}}</p>','name,event,startsAt,endsAt,timeZone,venue,message','PUBLISHED',0,CURRENT_TIMESTAMP,0);
INSERT INTO communication_templates(id,template_key,name,subject,html,variables,status,version,updated_at,published_version) VALUES
 ('10000000-0000-0000-0000-000000000005','events.reminder','Events · Your gathering is coming up','Your gathering is coming up: {{event}}','<h2>Hello {{name}}</h2><p>Your gathering is coming up.</p><p>{{event}} · {{startsAt}} to {{endsAt}} · {{timeZone}}</p><p>{{venue}}</p><p>{{message}}</p>','name,event,startsAt,endsAt,timeZone,venue,message','PUBLISHED',0,CURRENT_TIMESTAMP,0);
INSERT INTO communication_templates(id,template_key,name,subject,html,variables,status,version,updated_at,published_version) VALUES
 ('10000000-0000-0000-0000-000000000006','events.cancellation','Events · Registration cancelled','Registration cancelled: {{event}}','<h2>Hello {{name}}</h2><p>Registration cancelled.</p><p>{{event}} · {{startsAt}} to {{endsAt}} · {{timeZone}}</p><p>{{venue}}</p><p>{{message}}</p>','name,event,startsAt,endsAt,timeZone,venue,message','PUBLISHED',0,CURRENT_TIMESTAMP,0);
INSERT INTO communication_templates(id,template_key,name,subject,html,variables,status,version,updated_at,published_version) VALUES
 ('10000000-0000-0000-0000-000000000007','events.rescheduled','Events · Your gathering has changed','Your gathering has changed: {{event}}','<h2>Hello {{name}}</h2><p>Your gathering has changed.</p><p>{{event}} · {{startsAt}} to {{endsAt}} · {{timeZone}}</p><p>{{venue}}</p><p>{{message}}</p>','name,event,startsAt,endsAt,timeZone,venue,message','PUBLISHED',0,CURRENT_TIMESTAMP,0);
INSERT INTO communication_template_revisions(template_id,version,subject,html,variables,published_at) SELECT id,version,subject,html,variables,updated_at FROM communication_templates WHERE template_key LIKE 'events.%';
