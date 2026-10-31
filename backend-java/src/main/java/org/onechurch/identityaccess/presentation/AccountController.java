package org.onechurch.identityaccess.presentation;

import org.onechurch.identityaccess.application.AccountView;
import org.onechurch.identityaccess.application.command.changepassword.ChangePasswordCommand;
import org.onechurch.identityaccess.application.command.changepassword.ChangePasswordHandler;
import org.onechurch.identityaccess.application.command.createaccount.CreateAccountCommand;
import org.onechurch.identityaccess.application.command.createaccount.CreateAccountHandler;
import org.onechurch.identityaccess.application.command.setaccountrole.SetAccountRoleCommand;
import org.onechurch.identityaccess.application.command.setaccountrole.SetAccountRoleHandler;
import org.onechurch.identityaccess.application.command.setaccountstatus.SetAccountStatusCommand;
import org.onechurch.identityaccess.application.command.setaccountstatus.SetAccountStatusHandler;
import org.onechurch.identityaccess.application.command.updateaccount.UpdateAccountCommand;
import org.onechurch.identityaccess.application.command.updateaccount.UpdateAccountHandler;
import org.onechurch.identityaccess.application.query.getaccount.GetAccountHandler;
import org.onechurch.identityaccess.application.query.getaccount.GetAccountQuery;
import org.onechurch.identityaccess.application.query.listaccounts.ListAccountsHandler;
import org.onechurch.identityaccess.application.query.listaccounts.ListAccountsQuery;
import org.onechurch.identityaccess.presentation.api.AccountApi;
import org.springframework.http.ResponseEntity;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.Set;
import java.util.UUID;

@RestController
@PreAuthorize("hasAuthority('identity.manage')")
public class AccountController implements AccountApi {

    private final CreateAccountHandler create;
    private final UpdateAccountHandler update;
    private final SetAccountStatusHandler status;
    private final ChangePasswordHandler passwords;
    private final SetAccountRoleHandler roles;
    private final GetAccountHandler get;
    private final ListAccountsHandler list;

    public AccountController(CreateAccountHandler create, UpdateAccountHandler update, SetAccountStatusHandler status,
        ChangePasswordHandler passwords, SetAccountRoleHandler roles, GetAccountHandler get, ListAccountsHandler list) {
        this.create = create;
        this.update = update;
        this.status = status;
        this.passwords = passwords;
        this.roles = roles;
        this.get = get;
        this.list = list;
    }

    @Override
    public Page list(String search, int limit) {
        return new Page(list.handle(new ListAccountsQuery(search, limit)));
    }

    @Override
    public AccountView get(UUID id) {
        return get.handle(new GetAccountQuery(id));
    }

    @Override
    public Set<String> permissions(UUID id) {
        return get.handle(new GetAccountQuery(id)).permissions();
    }

    @Override
    public Created create(OAuth2AuthenticatedPrincipal actor, Create request) {
        return new Created(create.handle(new CreateAccountCommand(actor(actor), request.username(), request.email(),
            request.displayName(), request.password())));
    }

    @Override
    public ResponseEntity<Void> update(OAuth2AuthenticatedPrincipal actor, UUID id, Profile request) {
        update.handle(new UpdateAccountCommand(actor(actor), id, request.email(), request.displayName()));
        return ResponseEntity.noContent().build();
    }

    @Override
    public ResponseEntity<Void> password(OAuth2AuthenticatedPrincipal actor, UUID id, Password request) {
        passwords.handle(new ChangePasswordCommand(actor(actor), id, null, request.password(), true));
        return ResponseEntity.noContent().build();
    }

    @Override
    public ResponseEntity<Void> disable(OAuth2AuthenticatedPrincipal actor, UUID id) {
        status.handle(new SetAccountStatusCommand(actor(actor), id, false));
        return ResponseEntity.noContent().build();
    }

    @Override
    public ResponseEntity<Void> enable(OAuth2AuthenticatedPrincipal actor, UUID id) {
        status.handle(new SetAccountStatusCommand(actor(actor), id, true));
        return ResponseEntity.noContent().build();
    }

    @Override
    public ResponseEntity<Void> assign(OAuth2AuthenticatedPrincipal actor, UUID id, UUID roleId) {
        roles.handle(new SetAccountRoleCommand(actor(actor), id, roleId, true));
        return ResponseEntity.noContent().build();
    }

    @Override
    public ResponseEntity<Void> remove(OAuth2AuthenticatedPrincipal actor, UUID id, UUID roleId) {
        roles.handle(new SetAccountRoleCommand(actor(actor), id, roleId, false));
        return ResponseEntity.noContent().build();
    }

    private static UUID actor(OAuth2AuthenticatedPrincipal actor) {
        return UUID.fromString(actor.getName());
    }
}
