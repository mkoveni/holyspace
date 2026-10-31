-- Reapply after every migration or restore. Operator SQL, never application data access.
GRANT USAGE ON SCHEMA public TO holyspace_runtime;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO holyspace_runtime;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO holyspace_runtime;
ALTER DEFAULT PRIVILEGES FOR ROLE holyspace_migrator IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO holyspace_runtime;
ALTER DEFAULT PRIVILEGES FOR ROLE holyspace_migrator IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO holyspace_runtime;
REVOKE ALL ON TABLE public.flyway_schema_history FROM holyspace_runtime;
