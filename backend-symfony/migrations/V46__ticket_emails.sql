-- The email that carries a paid order's tickets (design 5.7). One per order; the evidence row lets Events re-judge the
-- frozen delivery before every send (order still paid, address and content unchanged).
CREATE TABLE event_ticket_emails (
    id UUID PRIMARY KEY,
    order_id UUID NOT NULL UNIQUE REFERENCES event_ticket_orders(id),
    delivery_id UUID NOT NULL UNIQUE,
    review_hash CHAR(64) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL
);
INSERT INTO communication_templates(id,template_key,name,subject,html,variables,status,version,updated_at,published_version) VALUES
 ('10000000-0000-0000-0000-00000000000d','events.tickets','Events · Your tickets','Your tickets for {{event}}',
 '<h2>Thank you, {{name}}</h2><p>Your {{seats}} ticket(s) for {{event}} are ready.</p><p>{{startsAt}} · {{timeZone}} · {{venue}}</p><p>Total paid: {{total}} (reference {{reference}}).</p><p><a href="{{link}}">Open your tickets and QR codes</a></p><p>Show the QR code on your phone at the door. Keep this email: the link above is your wallet.</p>',
 'name,event,startsAt,timeZone,venue,seats,total,reference,link','PUBLISHED',0,CURRENT_TIMESTAMP,0);
INSERT INTO communication_template_revisions(template_id,version,subject,html,variables,published_at)
 SELECT id,version,subject,html,variables,updated_at FROM communication_templates WHERE template_key='events.tickets';
