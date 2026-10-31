package org.onechurch.payments.presentation;
import org.onechurch.payments.application.command.checkproviderconnection.CheckProviderConnectionCommand;
import org.onechurch.payments.application.command.checkproviderconnection.CheckProviderConnectionHandler;
import org.onechurch.payments.application.command.saveprovidersettings.SaveProviderSettingsCommand;
import org.onechurch.payments.application.command.saveprovidersettings.SaveProviderSettingsHandler;
import org.onechurch.payments.application.query.getpaymentsettings.GetPaymentSettingsHandler;
import org.onechurch.payments.application.query.getpaymentsettings.GetPaymentSettingsQuery;
import org.onechurch.payments.domain.ConfigurationCheck;
import org.onechurch.payments.presentation.api.PaymentSettingsApi;
import org.springframework.http.CacheControl;
import org.springframework.http.ResponseEntity;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.UUID;
@RestController
public class PaymentSettingsController implements PaymentSettingsApi {
    private final GetPaymentSettingsHandler get;
    private final SaveProviderSettingsHandler save;
    private final CheckProviderConnectionHandler check;
    public PaymentSettingsController(GetPaymentSettingsHandler get, SaveProviderSettingsHandler save,
        CheckProviderConnectionHandler check) {
        this.get = get;
        this.save = save;
        this.check = check;
    }
    @Override
    @PreAuthorize("hasAuthority('payments.configure')")
    public ResponseEntity<GetPaymentSettingsHandler.Result> settings() {
        return ResponseEntity.ok().cacheControl(CacheControl.noStore()).body(get.handle(new GetPaymentSettingsQuery()));
    }
    @Override
    @PreAuthorize("hasAuthority('payments.configure')")
    public void save(String provider, SaveBody b, OAuth2AuthenticatedPrincipal actor) {
        save.handle(new SaveProviderSettingsCommand(UUID.fromString(actor.getName()), provider, b.mode(),
            b.expectedVersion(), b.enabled(), b.monthly(), b.publicUrl(), b.ipRanges(), b.credentials().credentials(),
            b.reason(), b.backendUrl()));
    }
    @Override
    @PreAuthorize("hasAuthority('payments.configure')")
    public ResponseEntity<ConfigurationCheck> check(String provider, CheckBody b, OAuth2AuthenticatedPrincipal actor) {
        return ResponseEntity.ok()
            .cacheControl(CacheControl.noStore())
            .body(check.handle(new CheckProviderConnectionCommand(UUID.fromString(actor.getName()), provider, b.mode(),
                b.expectedVersion())));
    }
}
