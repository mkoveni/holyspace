package org.onechurch.communications.application.query.getmynotificationpreferences;
import org.onechurch.communications.application.contract.InboxReader;
import org.onechurch.identityaccess.application.contract.AuthenticatedAccount;

import java.util.List;
import java.util.UUID;
public final class GetMyNotificationPreferencesHandler {
    private final AuthenticatedAccount account;
    private final InboxReader reader;
    public GetMyNotificationPreferencesHandler(AuthenticatedAccount account, InboxReader reader) {
        this.account = account;
        this.reader = reader;
    }
    public List<InboxReader.MemberNotificationPreference> handle(GetMyNotificationPreferencesQuery query) {
        UUID caller = account.requireAccount();
        return reader.preferences(caller);
    }
}
