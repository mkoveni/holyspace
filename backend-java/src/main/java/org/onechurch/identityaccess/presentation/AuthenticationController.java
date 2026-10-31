package org.onechurch.identityaccess.presentation;

import org.onechurch.identityaccess.application.AccountView;
import org.onechurch.identityaccess.application.command.changepassword.ChangePasswordCommand;
import org.onechurch.identityaccess.application.command.changepassword.ChangePasswordHandler;
import org.onechurch.identityaccess.application.command.login.LoginCommand;
import org.onechurch.identityaccess.application.command.login.LoginHandler;
import org.onechurch.identityaccess.application.command.logout.LogoutCommand;
import org.onechurch.identityaccess.application.command.logout.LogoutHandler;
import org.onechurch.identityaccess.application.query.getaccount.GetAccountHandler;
import org.onechurch.identityaccess.application.query.getaccount.GetAccountQuery;
import org.onechurch.identityaccess.presentation.api.AuthenticationApi;
import org.springframework.http.ResponseEntity;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.UUID;

@RestController
public class AuthenticationController implements AuthenticationApi {

    private final LoginHandler login;
    private final LogoutHandler logout;
    private final GetAccountHandler account;
    private final ChangePasswordHandler passwords;

    public AuthenticationController(LoginHandler login, LogoutHandler logout, GetAccountHandler account,
        ChangePasswordHandler passwords) {
        this.login = login;
        this.logout = logout;
        this.account = account;
        this.passwords = passwords;
    }

    @Override
    public LoginHandler.Result login(Login request) {
        return login.handle(new LoginCommand(request.username(), request.password(), request.deviceName()));
    }

    @Override
    public AccountView me(OAuth2AuthenticatedPrincipal principal) {
        return account.handle(new GetAccountQuery(UUID.fromString(principal.getName())));
    }

    @Override
    public ResponseEntity<Void> logout(OAuth2AuthenticatedPrincipal principal) {
        logout.handle(new LogoutCommand(UUID.fromString(principal.getName()),
            UUID.fromString(principal.getAttribute("session_id"))));
        return ResponseEntity.noContent().build();
    }

    @Override
    public ResponseEntity<Void> changePassword(OAuth2AuthenticatedPrincipal principal, PasswordChange request) {
        UUID id = UUID.fromString(principal.getName());
        passwords.handle(new ChangePasswordCommand(id, id, request.currentPassword(), request.password(), false));
        return ResponseEntity.noContent().build();
    }
}
