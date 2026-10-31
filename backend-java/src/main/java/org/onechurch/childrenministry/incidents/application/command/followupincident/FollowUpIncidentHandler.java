package org.onechurch.childrenministry.incidents.application.command.followupincident;
import org.onechurch.childrenministry.incidents.domain.Incident;
import org.onechurch.childrenministry.incidents.domain.IncidentEvent;
import org.onechurch.childrenministry.incidents.domain.repository.IncidentRepository;
import org.onechurch.childrenministry.safeguarding.application.contract.SafeguardingReader;
import org.onechurch.childrenministry.safeguarding.domain.GuardianAuthority;
import org.onechurch.childrenministry.safeguarding.domain.repository.SafeguardingRepository;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.identityaccess.application.contract.ResponsibleAccountDirectory;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.time.LocalDate;
import java.time.ZoneId;
import java.util.List;
import java.util.Locale;
import java.util.UUID;
public final class FollowUpIncidentHandler {
    private final PermissionChecker auth;
    private final IncidentRepository repository;
    private final SafeguardingReader safeguarding;
    private final SafeguardingRepository guardianLocks;
    private final ResponsibleAccountDirectory accounts;
    private final PersonDirectory people;
    private final UnitOfWork tx;
    private final ChildrenAudit audit;
    private final Clock clock;
    private final ZoneId zone;
    public FollowUpIncidentHandler(PermissionChecker auth, IncidentRepository repository,
        SafeguardingReader safeguarding, ResponsibleAccountDirectory accounts, PersonDirectory people, UnitOfWork tx,
        ChildrenAudit audit, Clock clock, ZoneId zone, SafeguardingRepository guardianLocks) {
        this.auth = auth;
        this.repository = repository;
        this.safeguarding = safeguarding;
        this.accounts = accounts;
        this.people = people;
        this.tx = tx;
        this.audit = audit;
        this.clock = clock;
        this.zone = zone;
        this.guardianLocks = guardianLocks;
    }
    public void handle(FollowUpIncidentCommand c) {
        auth.requirePermission(c.actorId(), "children.incidents.manage");
        String note = ChildrenRules.text(c.note(), 3000);
        if (c.action() == null
            || !List.of("TRIAGED", "ACTION", "GUARDIAN_CONTACT", "CLOSED", "REOPENED").contains(c.action())) {
            throw ChildrenException.invalid("Choose an incident follow-up action.");
        }
        tx.execute(() -> {
            Incident current = repository.lock(c.incidentId()).orElseThrow(() -> ChildrenException.missing("Incident"));
            if (c.action().equals("GUARDIAN_CONTACT")) {
                if (c.guardianAuthorityId() == null) {
                    throw ChildrenException.invalid("Choose current verified guardian authority.");
                }
                guardianLocks.guardianAuthorityForUpdate(c.guardianAuthorityId())
                    .orElseThrow(() -> ChildrenException.invalid("Choose current verified guardian authority."));
            }
            Instant now = clock.instant();
            LocalDate on = now.atZone(zone).toLocalDate();
            if (current.retainUntil().isBefore(on)) {
                throw ChildrenException.missing("Current incident");
            }
            Incident changed;
            if (c.action().equals("TRIAGED")) {
                if (c.ownerId() == null || !accounts.eligible(c.ownerId(), "children.incidents.manage")) {
                    throw ChildrenException.invalid("Choose an active account with incident-management access.");
                }
                changed = current.triage(c.expectedVersion(), c.ownerId(), c.reviewOn(), on);
            } else {
                changed = current.progress(c.expectedVersion(), c.action(), on);
            }
            UUID guardian = null;
            if (c.action().equals("GUARDIAN_CONTACT")) {
                GuardianAuthority authority = safeguarding.guardianAuthority(c.guardianAuthorityId())
                    .filter(value -> value.childId().equals(current.childId()) && value.covers(now, now))
                    .orElseThrow(() -> ChildrenException.invalid("Choose current verified guardian authority."));
                PersonDirectory.Entry person = people.get(authority.guardianId())
                    .filter(PersonDirectory.Entry::available)
                    .orElseThrow(() -> ChildrenException.invalid("Guardian identity is unavailable."));
                ChildrenRules.adult(person.birthDate(), on);
                guardian = person.id();
            }
            repository.update(changed);
            repository.append(new IncidentEvent(UUID.randomUUID(), changed.id(), changed.version(), c.action(),
                c.actorId(), now, note, guardian));
            audit.record(c.actorId(), "incident.followup." + c.action().toLowerCase(Locale.ROOT), changed.id(), "",
                now);
            return null;
        });
    }
}
