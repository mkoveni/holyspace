-- @statement
CREATE TABLE audit_entries (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    transaction_id text NOT NULL,
    request_id text,
    context varchar(40) NOT NULL,
    table_name text NOT NULL,
    record_id text NOT NULL,
    kind text NOT NULL,
    operation varchar(10) NOT NULL CHECK (operation IN ('created', 'updated', 'deleted')),
    version_before integer,
    version_after integer,
    actor_id text,
    source varchar(20) NOT NULL,
    occurred_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    changes jsonb NOT NULL CHECK (jsonb_typeof(changes) = 'object'),
    schema_version integer NOT NULL DEFAULT 1,
    redaction_version integer NOT NULL DEFAULT 1
);
-- @statement
CREATE INDEX idx_audit_record ON audit_entries (context, record_id, id DESC);
-- @statement
CREATE INDEX idx_audit_actor ON audit_entries (actor_id, id DESC);
-- @statement

-- JSON Pointer paths distinguish field names containing dots or slashes.
CREATE FUNCTION audit_sensitive(field_name text) RETURNS boolean
LANGUAGE sql IMMUTABLE SET search_path = pg_catalog AS $$
    SELECT field_name ~* '(password|secret|token|hash|credential|signature|authorization|recovery.?codes|content|notes?|body|subject|answers|provider.?reference|subscription.?id|request.?key|public.?key|merchant.?key|passphrase|pickup.?code|api.?key|private.?key|signing.?key|cookie|headers)'
$$;
-- @statement

CREATE FUNCTION audit_safe(value jsonb) RETURNS jsonb
LANGUAGE plpgsql IMMUTABLE SET search_path = pg_catalog AS $$
DECLARE result jsonb; pair record; element jsonb;
BEGIN
    IF jsonb_typeof(value) = 'object' THEN
        result := '{}'::jsonb;
        FOR pair IN SELECT * FROM jsonb_each(value) LOOP
            result := result || jsonb_build_object(pair.key, CASE WHEN public.audit_sensitive(pair.key) THEN '"[redacted]"'::jsonb ELSE public.audit_safe(pair.value) END);
        END LOOP;
        RETURN result;
    ELSIF jsonb_typeof(value) = 'array' THEN
        result := '[]'::jsonb;
        FOR element IN SELECT * FROM jsonb_array_elements(value) LOOP
            result := result || jsonb_build_array(public.audit_safe(element));
        END LOOP;
        RETURN result;
    END IF;
    RETURN value;
END
$$;
-- @statement

CREATE FUNCTION audit_diff(before_value jsonb, after_value jsonb, base_path text DEFAULT '') RETURNS jsonb
LANGUAGE plpgsql IMMUTABLE SET search_path = pg_catalog AS $$
DECLARE changes jsonb := '{}'; field_name text; old_value jsonb; new_value jsonb; field_path text; old_present boolean; new_present boolean;
BEGIN
    FOR field_name IN SELECT key FROM jsonb_object_keys(COALESCE(before_value, '{}')) AS key UNION SELECT key FROM jsonb_object_keys(COALESCE(after_value, '{}')) AS key LOOP
        old_present := COALESCE(before_value ? field_name, false);
        new_present := COALESCE(after_value ? field_name, false);
        old_value := before_value -> field_name;
        new_value := after_value -> field_name;
        IF old_present = new_present AND old_value IS NOT DISTINCT FROM new_value THEN CONTINUE; END IF;
        field_path := base_path || '/' || replace(replace(field_name, '~', '~0'), '/', '~1');
        IF NOT public.audit_sensitive(field_name) AND jsonb_typeof(old_value) = 'object' AND jsonb_typeof(new_value) = 'object' THEN
            changes := changes || public.audit_diff(old_value, new_value, field_path);
        ELSE
            changes := changes || jsonb_build_object(field_path, jsonb_build_object(
                'fromPresent', old_present, 'toPresent', new_present,
                'from', CASE WHEN old_present AND public.audit_sensitive(field_name) THEN '"[redacted]"'::jsonb ELSE public.audit_safe(old_value) END,
                'to', CASE WHEN new_present AND public.audit_sensitive(field_name) THEN '"[redacted]"'::jsonb ELSE public.audit_safe(new_value) END
            ));
        END IF;
    END LOOP;
    RETURN changes;
END
$$;
-- @statement

CREATE FUNCTION audit_capture() RETURNS trigger
LANGUAGE plpgsql SECURITY DEFINER SET search_path = pg_catalog AS $$
DECLARE old_row jsonb; new_row jsonb; row_value jsonb; entry_changes jsonb; record_key text; row_kind text;
BEGIN
    old_row := CASE WHEN TG_OP = 'INSERT' THEN NULL ELSE to_jsonb(OLD) END;
    new_row := CASE WHEN TG_OP = 'DELETE' THEN NULL ELSE to_jsonb(NEW) END;
    row_value := COALESCE(new_row, old_row);
    row_kind := COALESCE(row_value ->> 'kind', TG_TABLE_NAME);
    -- Support notes and arbitrary form answers have stricter policies than ordinary documents.
    IF TG_TABLE_NAME = 'childrenministry_records' AND row_kind IN ('supportnotes', 'labels') THEN
        IF old_row IS NOT NULL THEN old_row := jsonb_set(old_row, '{payload}', jsonb_build_object('content', old_row -> 'payload')); END IF;
        IF new_row IS NOT NULL THEN new_row := jsonb_set(new_row, '{payload}', jsonb_build_object('content', new_row -> 'payload')); END IF;
    END IF;
    entry_changes := public.audit_diff(old_row, new_row);
    IF entry_changes = '{}'::jsonb THEN RETURN NULL; END IF;
    record_key := CASE
        WHEN TG_TABLE_NAME = 'media_images' THEN row_value ->> 'owner_id'
        WHEN TG_TABLE_NAME IN ('people_family_memberships', 'people_family_addresses') THEN row_value ->> 'family_id'
        WHEN TG_TABLE_NAME IN ('people_person_addresses', 'people_communication_options', 'people_education_history', 'people_employment_history') THEN row_value ->> 'person_id'
        WHEN TG_TABLE_NAME = 'life_event_participants' THEN row_value ->> 'life_event_id'
        WHEN TG_TABLE_NAME = 'user_roles' THEN row_value ->> 'user_id'
        WHEN TG_TABLE_NAME = 'role_permissions' THEN row_value ->> 'role_id'
        ELSE COALESCE(row_value ->> 'id', row_value ->> 'user_id') END;
    INSERT INTO public.audit_entries(transaction_id, request_id, context, table_name, record_id, kind, operation, version_before, version_after, actor_id, source, changes)
    VALUES (pg_current_xact_id()::text, nullif(current_setting('app.request_id', true), ''), TG_ARGV[0], TG_TABLE_NAME, record_key, row_kind,
        CASE TG_OP WHEN 'INSERT' THEN 'created' WHEN 'DELETE' THEN 'deleted' ELSE 'updated' END,
        (old_row ->> 'version')::integer, (new_row ->> 'version')::integer,
        nullif(current_setting('app.actor_id', true), ''), COALESCE(nullif(current_setting('app.source', true), ''), 'system'), entry_changes);
    RETURN NULL;
END
$$;
-- @statement

CREATE FUNCTION audit_immutable() RETURNS trigger
LANGUAGE plpgsql SET search_path = pg_catalog AS $$
BEGIN
    RAISE EXCEPTION 'Audit history is append-only';
END
$$;
-- @statement
CREATE TRIGGER audit_no_changes BEFORE UPDATE OR DELETE OR TRUNCATE ON audit_entries FOR EACH STATEMENT EXECUTE FUNCTION audit_immutable();
-- @statement
REVOKE ALL ON audit_entries FROM PUBLIC;
-- @statement
REVOKE ALL ON FUNCTION audit_capture() FROM PUBLIC;
-- @statement
