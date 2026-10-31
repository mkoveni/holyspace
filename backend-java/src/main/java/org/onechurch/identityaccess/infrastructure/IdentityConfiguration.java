package org.onechurch.identityaccess.infrastructure;

import org.onechurch.identityaccess.application.authorization.Authorization;
import org.onechurch.identityaccess.application.command.bootstrapadministrator.BootstrapAdministratorHandler;
import org.onechurch.identityaccess.application.command.changepassword.ChangePasswordHandler;
import org.onechurch.identityaccess.application.command.createaccount.CreateAccountHandler;
import org.onechurch.identityaccess.application.command.createpermission.CreatePermissionHandler;
import org.onechurch.identityaccess.application.command.createrole.CreateRoleHandler;
import org.onechurch.identityaccess.application.command.login.LoginHandler;
import org.onechurch.identityaccess.application.command.logout.LogoutHandler;
import org.onechurch.identityaccess.application.command.setaccountrole.SetAccountRoleHandler;
import org.onechurch.identityaccess.application.command.setaccountstatus.SetAccountStatusHandler;
import org.onechurch.identityaccess.application.command.setrolepermission.SetRolePermissionHandler;
import org.onechurch.identityaccess.application.command.updateaccount.UpdateAccountHandler;
import org.onechurch.identityaccess.application.contract.AccessReader;
import org.onechurch.identityaccess.application.contract.AccessWriter;
import org.onechurch.identityaccess.application.contract.AccountReader;
import org.onechurch.identityaccess.application.contract.CurrentActor;
import org.onechurch.identityaccess.application.contract.IdentityAuditWriter;
import org.onechurch.identityaccess.application.contract.LoginAttemptWriter;
import org.onechurch.identityaccess.application.contract.PasswordHasher;
import org.onechurch.identityaccess.application.contract.SessionReader;
import org.onechurch.identityaccess.application.contract.SessionWriter;
import org.onechurch.identityaccess.application.contract.TokenCodec;
import org.onechurch.identityaccess.application.query.authenticatesession.AuthenticateSessionHandler;
import org.onechurch.identityaccess.application.query.getaccount.GetAccountHandler;
import org.onechurch.identityaccess.application.query.listaccounts.ListAccountsHandler;
import org.onechurch.identityaccess.application.query.listpermissions.ListPermissionsHandler;
import org.onechurch.identityaccess.application.query.listroles.ListRolesHandler;
import org.onechurch.identityaccess.domain.repository.AccountRepository;
import org.onechurch.shared.application.UnitOfWork;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import java.time.Clock;
import java.time.Duration;

@Configuration
public class IdentityConfiguration {

    @Bean
    Authorization identityAuthorization(CurrentActor currentActor, AccountReader accounts, AccessReader access,
        SessionReader sessions, Clock clock) {
        return new Authorization(currentActor, accounts, access, sessions, clock);
    }

    @Bean
    Clock identityClock() {
        return Clock.systemUTC();
    }

    @Bean
    LoginHandler loginHandler(AccountReader accounts, AccountRepository repository, LoginAttemptWriter attempts,
        SessionWriter sessions, PasswordHasher passwords, TokenCodec tokens, UnitOfWork transactions, Clock clock,
        @Value("${onechurch.security.session-lifetime:8h}") Duration lifetime) {
        if (lifetime.compareTo(Duration.ofMinutes(1)) < 0 || lifetime.compareTo(Duration.ofDays(1)) > 0) {
            throw new IllegalArgumentException("Session lifetime must be between one minute and one day.");
        }
        return new LoginHandler(accounts, repository, attempts, sessions, passwords, tokens, transactions, clock,
            lifetime);
    }

    @Bean
    GetAccountHandler getAccountHandler(AccountReader accounts, AccessReader access, Authorization authorization) {
        return new GetAccountHandler(accounts, access, authorization);
    }

    @Bean
    ListAccountsHandler listAccountsHandler(AccountReader accounts, Authorization authorization) {
        return new ListAccountsHandler(accounts, authorization);
    }

    @Bean
    ListRolesHandler listRolesHandler(AccessReader access, Authorization authorization) {
        return new ListRolesHandler(access, authorization);
    }

    @Bean
    ListPermissionsHandler listPermissionsHandler(AccessReader access, Authorization authorization) {
        return new ListPermissionsHandler(access, authorization);
    }

    @Bean
    AuthenticateSessionHandler authenticateSessionHandler(SessionReader sessions, AccountReader accounts,
        AccessReader access, TokenCodec tokens, Clock clock) {
        return new AuthenticateSessionHandler(sessions, accounts, access, tokens, clock);
    }

    @Bean
    CreateAccountHandler createAccountHandler(IdentityAuditWriter audit, AccountRepository repository,
        PasswordHasher passwords, UnitOfWork transactions, Clock clock, Authorization authorization) {
        return new CreateAccountHandler(audit, repository, passwords, transactions, clock, authorization);
    }

    @Bean
    BootstrapAdministratorHandler bootstrapAdministratorHandler(AccountReader reader, AccessWriter writer,
        IdentityAuditWriter audit, AccountRepository repository, PasswordHasher passwords, UnitOfWork transactions,
        Clock clock) {
        return new BootstrapAdministratorHandler(reader, writer, audit, repository, passwords, transactions, clock);
    }

    @Bean
    UpdateAccountHandler updateAccountHandler(AccountRepository reader, IdentityAuditWriter audit,
        UnitOfWork transactions, Clock clock, Authorization authorization) {
        return new UpdateAccountHandler(reader, audit, transactions, clock, authorization);
    }

    @Bean
    SetAccountStatusHandler setAccountStatusHandler(AccountRepository reader, AccessReader access, AccessWriter writer,
        IdentityAuditWriter audit, SessionWriter sessions, UnitOfWork transactions, Clock clock,
        Authorization authorization) {
        return new SetAccountStatusHandler(reader, access, writer, audit, sessions, transactions, clock, authorization);
    }

    @Bean
    ChangePasswordHandler changePasswordHandler(AccountRepository reader, IdentityAuditWriter audit,
        SessionWriter sessions, PasswordHasher passwords, UnitOfWork transactions, Clock clock,
        Authorization authorization) {
        return new ChangePasswordHandler(reader, audit, sessions, passwords, transactions, clock, authorization);
    }

    @Bean
    LogoutHandler logoutHandler(SessionWriter sessions, IdentityAuditWriter audit, UnitOfWork transactions, Clock clock,
        Authorization authorization) {
        return new LogoutHandler(sessions, audit, transactions, clock, authorization);
    }

    @Bean
    CreateRoleHandler createRoleHandler(AccessWriter writer, IdentityAuditWriter audit, UnitOfWork transactions,
        Clock clock, Authorization authorization) {
        return new CreateRoleHandler(writer, audit, transactions, clock, authorization);
    }

    @Bean
    CreatePermissionHandler createPermissionHandler(AccessWriter writer, IdentityAuditWriter audit,
        UnitOfWork transactions, Clock clock, Authorization authorization) {
        return new CreatePermissionHandler(writer, audit, transactions, clock, authorization);
    }

    @Bean
    SetAccountRoleHandler setAccountRoleHandler(AccountReader accounts, AccessReader access, AccessWriter writer,
        IdentityAuditWriter audit, UnitOfWork transactions, Clock clock, Authorization authorization) {
        return new SetAccountRoleHandler(accounts, access, writer, audit, transactions, clock, authorization);
    }

    @Bean
    SetRolePermissionHandler setRolePermissionHandler(AccountReader accounts, AccessReader access, AccessWriter writer,
        IdentityAuditWriter audit, UnitOfWork transactions, Clock clock, Authorization authorization) {
        return new SetRolePermissionHandler(accounts, access, writer, audit, transactions, clock, authorization);
    }
}
