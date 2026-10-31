package org.onechurch.people.application.command.endrelationship;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PeopleAuditWriter;
import org.onechurch.people.domain.PeopleException;
import org.onechurch.people.domain.PersonRelationship;
import org.onechurch.people.domain.repository.RelationshipRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;

public final class EndRelationshipHandler {

    private final PermissionChecker authorization;
    private final PeopleAuditWriter audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final RelationshipRepository relationships;

    public EndRelationshipHandler(PermissionChecker authorization, PeopleAuditWriter audit, UnitOfWork transactions,
        Clock clock, RelationshipRepository relationships) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.relationships = relationships;
    }

    public void handle(EndRelationshipCommand command) {
        authorization.requirePermission(command.actorId(), "people.manage");
        transactions.execute(() -> {
            relationships.lockGraph();
            Instant now = clock.instant();
            PersonRelationship relationship =
                relationships.find(command.relationshipId()).orElseThrow(() -> PeopleException.missing("Relationship"));
            relationships.update(relationship.end(command.expectedVersion(), now));
            audit.audit(command.actorId(), "relationship.ended", relationship.id(), now);
            return null;
        });
    }
}
