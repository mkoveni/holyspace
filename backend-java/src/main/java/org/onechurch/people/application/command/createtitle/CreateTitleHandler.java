package org.onechurch.people.application.command.createtitle;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PeopleAuditWriter;
import org.onechurch.people.application.contract.TitleWriter;
import org.onechurch.people.domain.PeopleException;
import org.onechurch.people.domain.PeopleRules;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;

public final class CreateTitleHandler {

    private final PermissionChecker authorization;
    private final PeopleAuditWriter audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final TitleWriter titleWriter;

    public CreateTitleHandler(PermissionChecker authorization, PeopleAuditWriter audit, UnitOfWork transactions,
        Clock clock, TitleWriter titleWriter) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.titleWriter = titleWriter;
    }

    public void handle(CreateTitleCommand command) {
        authorization.requirePermission(command.actorId(), "people.manage");
        transactions.execute(() -> {
            Instant now = clock.instant();
            String code = PeopleRules.titleCode(command.code());
            if (code == null) {
                throw PeopleException.invalid("Title code is required.");
            }
            titleWriter.add(code, PeopleRules.text(command.label(), "Title label", 40));
            audit.audit(command.actorId(), "title.created", code, now);
            return null;
        });
    }
}
