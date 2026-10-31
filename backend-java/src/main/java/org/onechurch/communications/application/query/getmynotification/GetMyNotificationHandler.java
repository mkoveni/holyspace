package org.onechurch.communications.application.query.getmynotification;
import org.onechurch.communications.application.contract.InboxReader;
import org.onechurch.communications.application.contract.PushReader;
import org.onechurch.communications.domain.CommunicationException;
import org.onechurch.identityaccess.application.contract.AuthenticatedAccount;

import java.util.UUID;
public final class GetMyNotificationHandler {
    private final AuthenticatedAccount account;
    private final PushReader reader;
    public GetMyNotificationHandler(AuthenticatedAccount account, PushReader reader) {
        this.account = account;
        this.reader = reader;
    }
    public InboxReader.MemberNotification handle(GetMyNotificationQuery query) {
        UUID id = account.requireAccount();
        return reader.notification(id, query.notificationId())
            .orElseThrow(() -> CommunicationException.missing("Notification"));
    }
}
