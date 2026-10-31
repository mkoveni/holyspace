package org.onechurch.communications.application.query.listmynotifications;
import org.onechurch.communications.application.contract.InboxReader;
import org.onechurch.communications.domain.CommunicationException;
import org.onechurch.identityaccess.application.contract.AuthenticatedAccount;

import java.util.UUID;
public final class ListMyNotificationsHandler {
    private final AuthenticatedAccount account;
    private final InboxReader reader;
    public ListMyNotificationsHandler(AuthenticatedAccount account, InboxReader reader) {
        this.account = account;
        this.reader = reader;
    }
    public InboxReader.MemberInbox handle(ListMyNotificationsQuery query) {
        UUID caller = account.requireAccount();
        if (query.limit() < 1 || query.limit() > 100 || query.offset() < 0 || query.offset() > 10000) {
            throw CommunicationException
                .invalid("Choose a page of 1–100 notifications within the first 10000 results.");
        }
        return reader.list(caller, query.unreadOnly(), query.limit(), query.offset());
    }
}
