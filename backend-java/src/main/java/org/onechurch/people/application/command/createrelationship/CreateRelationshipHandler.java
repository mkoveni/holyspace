package org.onechurch.people.application.command.createrelationship;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PeopleAuditWriter;
import org.onechurch.people.domain.ParentagePolicy;
import org.onechurch.people.domain.PeopleException;
import org.onechurch.people.domain.PersonRelationship;
import org.onechurch.people.domain.repository.PersonRepository;
import org.onechurch.people.domain.repository.RelationshipRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.util.Comparator;
import java.util.List;
import java.util.UUID;
import java.util.stream.Stream;

public final class CreateRelationshipHandler {

    private final PermissionChecker authorization;
    private final PeopleAuditWriter audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final RelationshipRepository relationships;
    private final PersonRepository people;

    public CreateRelationshipHandler(PermissionChecker authorization, PeopleAuditWriter audit, UnitOfWork transactions,
        Clock clock, RelationshipRepository relationships, PersonRepository people) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.relationships = relationships;
        this.people = people;
    }

    public UUID handle(CreateRelationshipCommand command) {
        authorization.requirePermission(command.actorId(), "people.manage");
        return transactions.execute(() -> {
            relationships.lockGraph();
            Instant now = clock.instant();
            List<UUID> ids = Stream.of(command.sourceId(), command.targetId())
                .distinct()
                .sorted(Comparator.comparing(UUID::toString))
                .toList();
            for (UUID id : ids) {
                people.findForUpdate(id).orElseThrow(() -> PeopleException.missing("Person")).requireAvailable();
            }
            PersonRelationship relationship = new PersonRelationship(UUID.randomUUID(), command.sourceId(),
                command.targetId(), command.type(), now, null, 0);
            ParentagePolicy.requireAcyclic(relationship, relationships.currentParentage(now), now);
            relationships.insert(relationship);
            audit.audit(command.actorId(), "relationship.created", relationship.id(), now);
            return relationship.id();
        });
    }
}
