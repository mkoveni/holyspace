package org.onechurch.communications.application.command.setmynotificationpreference;
import org.onechurch.communications.application.contract.InboxWriter;
import org.onechurch.communications.domain.CommunicationException;
import org.onechurch.identityaccess.application.contract.AuthenticatedAccount;
import org.onechurch.shared.application.UnitOfWork;

import java.util.Set;
import java.util.UUID;
public final class SetMyNotificationPreferenceHandler {
    private final AuthenticatedAccount account;
    private final InboxWriter writer;
    private final UnitOfWork tx;
    public SetMyNotificationPreferenceHandler(AuthenticatedAccount account, InboxWriter writer, UnitOfWork tx) {
        this.account = account;
        this.writer = writer;
        this.tx = tx;
    }
    public void handle(SetMyNotificationPreferenceCommand command) {
        UUID caller = account.requireAccount();
        tx.execute(() -> {
            account.requireAccount();
            if (command.topic() == null || !Set.of("VOLUNTEERING", "EVENTS").contains(command.topic())
                || command.expectedVersion() < 0) {
                throw CommunicationException.invalid("Choose a supported topic and current preference version.");
            }
            writer.preference(caller, command.topic(), command.enabled(), command.expectedVersion());
            account.requireAccount();
            return null;
        });
    }
}
