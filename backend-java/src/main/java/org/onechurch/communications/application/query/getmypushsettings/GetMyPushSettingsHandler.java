package org.onechurch.communications.application.query.getmypushsettings;
import org.onechurch.communications.application.contract.PushReader;
import org.onechurch.communications.application.contract.PushSettings;
import org.onechurch.identityaccess.application.contract.AuthenticatedAccount;
import org.onechurch.identityaccess.application.contract.CurrentActor;

import java.util.UUID;
public final class GetMyPushSettingsHandler {
    private final AuthenticatedAccount account;
    private final PushReader reader;
    private final PushSettings settings;
    private final CurrentActor actor;
    public GetMyPushSettingsHandler(AuthenticatedAccount account, PushReader reader, PushSettings settings,
        CurrentActor actor) {
        this.account = account;
        this.reader = reader;
        this.settings = settings;
        this.actor = actor;
    }
    public PushReader.MemberPushSettings handle(GetMyPushSettingsQuery query) {
        UUID id = account.requireAccount();
        return new PushReader.MemberPushSettings(settings.enabled(), settings.projectId(), reader.preferences(id),
            reader.devices(id, actor.current().orElseThrow().sessionId()));
    }
}
