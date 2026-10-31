package org.onechurch.communications.application.command.setmynotificationread;
import org.onechurch.communications.application.contract.InboxWriter;
import org.onechurch.identityaccess.application.contract.AuthenticatedAccount;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.util.UUID;
public final class SetMyNotificationReadHandler {
    private final AuthenticatedAccount account;
    private final InboxWriter writer;
    private final UnitOfWork tx;
    private final Clock clock;
    public SetMyNotificationReadHandler(AuthenticatedAccount account, InboxWriter writer, UnitOfWork tx, Clock clock) {
        this.account = account;
        this.writer = writer;
        this.tx = tx;
        this.clock = clock;
    }
    public void handle(SetMyNotificationReadCommand command) {
        UUID caller = account.requireAccount();
        tx.execute(() -> {
            account.requireAccount();
            writer.read(caller, command.notificationId(), command.read(), clock.instant());
            account.requireAccount();
            return null;
        });
    }
}
