-- Aggregate operator checks: never print recipient, child or financial payloads.
SELECT
 (SELECT count(*) FROM communication_email_deliveries
  WHERE status IN ('FAILED','UNKNOWN')
     OR (status IN ('QUEUED','SCHEDULED') AND scheduled_at < now() - interval '15 minutes')
     OR (status='SENDING' AND started_at < now() - interval '5 minutes')) AS email_attention,
 (SELECT count(*) FROM communication_push_deliveries
  WHERE status='UNKNOWN' OR (status='FAILED' AND error_code <> 'DeviceNotRegistered')
     OR (status IN ('QUEUED','RETRY','TICKET') AND due_at < now() - interval '15 minutes')
     OR (status IN ('SENDING','CHECKING') AND lease_until < now() - interval '5 minutes')) AS push_attention,
 (SELECT count(*) FROM payments_charges
  WHERE mode='live' AND gift_id IS NULL AND created_at < now() - interval '5 minutes') AS unposted_live_payments,
 (SELECT count(*) FROM payments_refunds
  WHERE mode='live' AND NOT applied AND created_at < now() - interval '5 minutes') AS unapplied_live_refunds
\gset
SELECT :'email_attention'::bigint + :'push_attention'::bigint + :'unposted_live_payments'::bigint + :'unapplied_live_refunds'::bigint = 0 AS healthy
\gset
\echo email_attention=:email_attention push_attention=:push_attention unposted_live_payments=:unposted_live_payments unapplied_live_refunds=:unapplied_live_refunds
\if :healthy
\else
    \quit 1
\endif
