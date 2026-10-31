package org.onechurch.communications.application.command.setmypushpreference;
import org.onechurch.communications.application.contract.PushWriter;
import org.onechurch.communications.domain.CommunicationException;
import org.onechurch.identityaccess.application.contract.AuthenticatedAccount;
import org.onechurch.shared.application.UnitOfWork;

import java.util.Set;
import java.util.UUID;
public final class SetMyPushPreferenceHandler {
    private final AuthenticatedAccount account;
    private final PushWriter writer;
    private final UnitOfWork tx;
    public SetMyPushPreferenceHandler(AuthenticatedAccount account, PushWriter writer, UnitOfWork tx) {
        this.account = account;
        this.writer = writer;
        this.tx = tx;
    }
    public void handle(SetMyPushPreferenceCommand c) {
        UUID id = account.requireAccount();
        if (c.topic() == null || !Set.of("VOLUNTEERING", "EVENTS").contains(c.topic()) || c.expectedVersion() < 0) {
            throw CommunicationException.invalid("Choose a valid push topic and version.");
        }
        tx.execute(() -> {
            account.requireAccount();
            writer.preference(id, c.topic(), c.enabled(), c.expectedVersion());
            account.requireAccount();
            return null;
        });
    }
}
