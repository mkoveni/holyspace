package org.onechurch.childrenministry.messaging.application.command.createguardiancommunication;
import org.onechurch.childrenministry.messaging.application.GuardianCommunicationPolicy;
import org.onechurch.childrenministry.messaging.application.contract.GuardianCommunicationReader;
import org.onechurch.childrenministry.messaging.domain.GuardianCommunication;
import org.onechurch.childrenministry.messaging.domain.repository.GuardianCommunicationRepository;
import org.onechurch.childrenministry.participation.domain.Programme;
import org.onechurch.childrenministry.safeguarding.domain.GuardianAuthority;
import org.onechurch.childrenministry.safeguarding.domain.repository.SafeguardingRepository;
import org.onechurch.childrenministry.shared.application.RequestFingerprint;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.time.ZoneId;
import java.util.Optional;
import java.util.UUID;
public final class CreateGuardianCommunicationHandler {
    private final PermissionChecker auth;
    private final GuardianCommunicationPolicy policy;
    private final GuardianCommunicationReader reader;
    private final GuardianCommunicationRepository repository;
    private final SafeguardingRepository safeguarding;
    private final PersonDirectory people;
    private final UnitOfWork tx;
    private final ChildrenAudit audit;
    private final Clock clock;
    private final ZoneId zone;
    public CreateGuardianCommunicationHandler(PermissionChecker auth, GuardianCommunicationPolicy policy,
        GuardianCommunicationReader reader, GuardianCommunicationRepository repository,
        SafeguardingRepository safeguarding, PersonDirectory people, UnitOfWork tx, ChildrenAudit audit, Clock clock,
        ZoneId zone) {
        this.auth = auth;
        this.policy = policy;
        this.reader = reader;
        this.repository = repository;
        this.safeguarding = safeguarding;
        this.people = people;
        this.tx = tx;
        this.audit = audit;
        this.clock = clock;
        this.zone = zone;
    }
    public UUID handle(CreateGuardianCommunicationCommand c) {
        auth.requirePermission(c.actorId(), "children.safeguarding");
        if (c.requestId() == null || c.childId() == null || c.authorityId() == null || c.programmeId() == null
            || c.type() == null) {
            throw ChildrenException.invalid("Choose a child, verified guardian, programme and message type.");
        }
        String hash =
            RequestFingerprint.of(c.childId(), c.authorityId(), c.programmeId(), c.sessionId(), c.type(), c.dueOn());
        return tx.execute(() -> {
            people.lock(c.childId()).orElseThrow(() -> ChildrenException.missing("Child"));
            safeguarding.guardianAuthorityForUpdate(c.authorityId())
                .orElseThrow(() -> ChildrenException.missing("Guardian authority"));
            Optional<GuardianCommunicationReader.Receipt> receipt = reader.receipt(c.actorId(), c.requestId());
            if (receipt.isPresent()) {
                GuardianCommunicationReader.Receipt previous = receipt.orElseThrow();
                if (!previous.hash().equals(hash)) {
                    throw ChildrenException
                        .conflict("This communication request was already used for different details.");
                }
                return previous.id();
            }
            Instant now = clock.instant();
            if (c.dueOn() == null || c.dueOn().isBefore(now.atZone(zone).toLocalDate())) {
                throw ChildrenException.invalid("Choose a current or future contact date.");
            }
            GuardianAuthority guardian = policy.guardian(c.childId(), c.authorityId(), now);
            Programme programme = policy.programme(c.programmeId(), c.childId(), c.type(), c.sessionId(), now);
            if (c.type().equals("SESSION_REMINDER")
                && c.dueOn().isAfter(now.atZone(zone).toLocalDate().plusDays(365))) {
                throw ChildrenException.invalid("Choose a contact date within the next year.");
            }
            policy.contactDate(c.sessionId(), c.dueOn());
            UUID id = UUID.randomUUID();
            repository.insert(new GuardianCommunication(id, c.childId(), c.authorityId(), guardian.guardianId(),
                c.programmeId(), programme.currentPolicyId(), c.sessionId(), c.type(),
                policy.message(c.childId(), guardian.guardianId(), programme, c.type(), c.sessionId()), c.dueOn(),
                "DRAFT", c.actorId(), now, c.actorId(), now, "", 0), c.requestId(), hash);
            audit.record(c.actorId(), "guardian.communication.prepared", id, "", now);
            return id;
        });
    }
}
