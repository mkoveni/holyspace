package org.onechurch.communications.presentation;
import org.onechurch.communications.application.command.registermypushdevice.RegisterMyPushDeviceCommand;
import org.onechurch.communications.application.command.registermypushdevice.RegisterMyPushDeviceHandler;
import org.onechurch.communications.application.command.revokemypushdevice.RevokeMyPushDeviceCommand;
import org.onechurch.communications.application.command.revokemypushdevice.RevokeMyPushDeviceHandler;
import org.onechurch.communications.application.command.setmypushpreference.SetMyPushPreferenceCommand;
import org.onechurch.communications.application.command.setmypushpreference.SetMyPushPreferenceHandler;
import org.onechurch.communications.application.contract.InboxReader;
import org.onechurch.communications.application.contract.PushReader;
import org.onechurch.communications.application.query.getmynotification.GetMyNotificationHandler;
import org.onechurch.communications.application.query.getmynotification.GetMyNotificationQuery;
import org.onechurch.communications.application.query.getmypushsettings.GetMyPushSettingsHandler;
import org.onechurch.communications.application.query.getmypushsettings.GetMyPushSettingsQuery;
import org.onechurch.communications.presentation.api.MemberPushApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.web.bind.annotation.ModelAttribute;
import org.springframework.web.bind.annotation.RestController;

import jakarta.servlet.http.HttpServletResponse;

import java.util.UUID;
@RestController
@PreAuthorize("isAuthenticated()")
public class MemberPushController implements MemberPushApi {
    private final GetMyPushSettingsHandler settings;
    private final RegisterMyPushDeviceHandler register;
    private final RevokeMyPushDeviceHandler revoke;
    private final SetMyPushPreferenceHandler preference;
    private final GetMyNotificationHandler notification;
    public MemberPushController(GetMyPushSettingsHandler settings, RegisterMyPushDeviceHandler register,
        RevokeMyPushDeviceHandler revoke, SetMyPushPreferenceHandler preference,
        GetMyNotificationHandler notification) {
        this.settings = settings;
        this.register = register;
        this.revoke = revoke;
        this.preference = preference;
        this.notification = notification;
    }
    @ModelAttribute
    void noStore(HttpServletResponse response) {
        response.setHeader("Cache-Control", "no-store");
    }
    @Override
    public PushReader.MemberPushSettings settings() {
        return settings.handle(new GetMyPushSettingsQuery());
    }
    @Override
    public void register(UUID id, PushDeviceRegistrationRequest b) {
        register.handle(
            new RegisterMyPushDeviceCommand(id, b.installationSecret(), b.token(), b.platform(), b.projectId()));
    }
    @Override
    public void revoke(UUID id) {
        revoke.handle(new RevokeMyPushDeviceCommand(id));
    }
    @Override
    public void preference(String topic, PushTopicPreferenceRequest b) {
        preference.handle(new SetMyPushPreferenceCommand(topic, b.enabled(), b.expectedVersion()));
    }
    @Override
    public InboxReader.MemberNotification notification(UUID id) {
        return notification.handle(new GetMyNotificationQuery(id));
    }
}
