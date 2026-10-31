CREATE TABLE user_accounts (
    id UUID PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE CHECK (username = lower(username)),
    email VARCHAR(254) NOT NULL UNIQUE CHECK (email = lower(email)),
    display_name VARCHAR(160) NOT NULL CHECK (length(trim(display_name)) > 0),
    password_hash VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'disabled')),
    created_at TIMESTAMPTZ NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL
);

CREATE TABLE roles (
    id UUID PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE CHECK (name = lower(name)),
    description VARCHAR(255) NOT NULL DEFAULT ''
);
CREATE TABLE permissions (
    id UUID PRIMARY KEY,
    code VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NOT NULL DEFAULT ''
);
CREATE TABLE user_roles (
    user_id UUID NOT NULL REFERENCES user_accounts(id),
    role_id UUID NOT NULL REFERENCES roles(id),
    PRIMARY KEY (user_id, role_id)
);
CREATE TABLE role_permissions (
    role_id UUID NOT NULL REFERENCES roles(id),
    permission_id UUID NOT NULL REFERENCES permissions(id),
    PRIMARY KEY (role_id, permission_id)
);
CREATE TABLE account_sessions (
    id UUID PRIMARY KEY,
    account_id UUID NOT NULL REFERENCES user_accounts(id),
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at TIMESTAMPTZ NOT NULL,
    created_at TIMESTAMPTZ NOT NULL
);
CREATE INDEX account_sessions_account_idx ON account_sessions(account_id);
CREATE INDEX account_sessions_expiry_idx ON account_sessions(expires_at);
CREATE TABLE login_attempts (
    identifier_hash CHAR(64) PRIMARY KEY,
    failures INTEGER NOT NULL CHECK (failures > 0),
    resets_at TIMESTAMPTZ NOT NULL
);
CREATE TABLE identity_access_audit (
    id UUID PRIMARY KEY,
    actor_id UUID REFERENCES user_accounts(id),
    action VARCHAR(80) NOT NULL,
    target_id UUID NOT NULL,
    occurred_at TIMESTAMPTZ NOT NULL
);

INSERT INTO roles (id, name, description)
VALUES ('00000000-0000-0000-0000-000000000001', 'administrator', 'Manage accounts, roles and permissions');
INSERT INTO permissions (id, code, description)
VALUES ('00000000-0000-0000-0000-000000000002', 'identity.manage', 'Manage accounts, roles and permissions');
INSERT INTO role_permissions (role_id, permission_id)
VALUES ('00000000-0000-0000-0000-000000000001', '00000000-0000-0000-0000-000000000002');
