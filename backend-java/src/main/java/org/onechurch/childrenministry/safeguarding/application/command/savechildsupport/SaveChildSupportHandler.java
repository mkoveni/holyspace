package org.onechurch.childrenministry.safeguarding.application.command.savechildsupport;
import org.onechurch.childrenministry.safeguarding.application.contract.SupportReader;
import org.onechurch.childrenministry.safeguarding.domain.SupportRecord;
import org.onechurch.childrenministry.safeguarding.domain.repository.SupportRepository;
import org.onechurch.childrenministry.sessions.application.ParticipantAccess;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.time.LocalDate;
import java.time.ZoneId;
import java.util.Optional;

public final class SaveChildSupportHandler {
    private final PermissionChecker authorization;
    private final ChildrenAudit audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final ParticipantAccess participants;
    private final SupportReader support;
    private final SupportRepository supportRepository;
    private final ZoneId zone;
    public SaveChildSupportHandler(PermissionChecker authorization, ChildrenAudit audit, UnitOfWork transactions,
        Clock clock, ParticipantAccess participants, SupportReader support, SupportRepository supportRepository,
        ZoneId zone) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.participants = participants;
        this.support = support;
        this.supportRepository = supportRepository;
        this.zone = zone;
    }
    public void handle(SaveChildSupportCommand command) {
        authorization.requirePermission(command.actorId(), "children.manage");
        authorization.requirePermission(command.actorId(), "children.support");
        transactions.execute(() -> {
            participants.lockChild(command.childId());
            Instant now = clock.instant();
            LocalDate today = LocalDate.now(clock.withZone(zone));
            ChildrenRules.child(participants.available(command.childId()).birthDate(), today);
            Optional<SupportRecord> existing = supportRepository.supportRecordForUpdate(command.childId());
            if (command.reviewOn().isBefore(today) || command.retainUntil().isBefore(today)) {
                throw ChildrenException.invalid("Review and retention dates must not be in the past.");
            }
            if (existing.isEmpty()) {
                ChildrenRules.version(0, command.expectedVersion());
                supportRepository.insertSupportRecord(
                    new SupportRecord(command.childId(), command.allergies(), command.careInstructions(),
                        command.restrictedNotes(), command.reviewOn(), command.retainUntil(), 0, now));
            } else {
                SupportRecord previous = existing.orElseThrow();
                ChildrenRules.version(previous.version(), command.expectedVersion());
                supportRepository.updateSupportRecord(new SupportRecord(command.childId(), command.allergies(),
                    command.careInstructions(), command.restrictedNotes(), command.reviewOn(), command.retainUntil(),
                    previous.version() + 1, now));
            }
            audit.record(command.actorId(), "support.updated", command.childId(), "", now);
            return null;
        });
    }
}
