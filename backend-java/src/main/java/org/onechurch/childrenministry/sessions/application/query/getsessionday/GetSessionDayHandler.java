package org.onechurch.childrenministry.sessions.application.query.getsessionday;
import org.onechurch.childrenministry.sessions.application.AttendanceView;
import org.onechurch.childrenministry.sessions.application.SessionChildPreparation;
import org.onechurch.childrenministry.sessions.application.SessionOperations;
import org.onechurch.childrenministry.sessions.application.StaffScope;
import org.onechurch.childrenministry.sessions.application.contract.SessionDayReader;
import org.onechurch.childrenministry.sessions.application.contract.SessionReader;
import org.onechurch.childrenministry.sessions.domain.Attendance;
import org.onechurch.childrenministry.sessions.domain.MinistrySession;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonDirectory;

import java.time.Clock;
import java.time.Instant;
import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.function.Function;
import java.util.stream.Collectors;
import java.util.stream.Stream;
public final class GetSessionDayHandler {
    private final PermissionChecker authorization;
    private final StaffScope scope;
    private final SessionReader sessions;
    private final SessionDayReader reader;
    private final PersonDirectory people;
    private final SessionOperations operations;
    private final SessionChildPreparation preparation;
    private final Clock clock;
    public GetSessionDayHandler(PermissionChecker authorization, StaffScope scope, SessionReader sessions,
        SessionDayReader reader, PersonDirectory people, SessionOperations operations,
        SessionChildPreparation preparation, Clock clock) {
        this.authorization = authorization;
        this.scope = scope;
        this.sessions = sessions;
        this.reader = reader;
        this.people = people;
        this.operations = operations;
        this.preparation = preparation;
        this.clock = clock;
    }
    public SessionDayPage handle(GetSessionDayQuery query) {
        authorization.requirePermission(query.actorId(), "children.station");
        scope.require(query.actorId(), query.sessionId());
        ChildrenRules.page(query.limit(), query.offset());
        if (!List.of("EXPECTED", "ATTENDING", "HISTORY").contains(query.view())) {
            throw ChildrenException.invalid("Choose a valid station view.");
        }
        String search = ChildrenRules.optional(query.search(), 120);
        List<UUID> matching = search.isEmpty() ? null : people.matchingIds(search);
        MinistrySession session =
            sessions.ministrySession(query.sessionId()).orElseThrow(() -> ChildrenException.missing("Session"));
        Instant now = clock.instant();
        if (query.view().equals("EXPECTED")) {
            SessionDayReader.ExpectedPage page =
                reader.expected(session.classId(), operations.date(session), matching, query.limit(), query.offset());
            Map<UUID, PersonDirectory.Entry> names = names(page.childIds());
            Map<UUID, List<String>> issues = preparation.issues(session, names, now);
            Map<UUID, Attendance> current = reader.current(session.id(), page.childIds());
            return new SessionDayPage(page.childIds()
                .stream()
                .map(id -> new SessionDayPage.Child(id, names.get(id).name(),
                    current.containsKey(id) ? AttendanceView.from(current.get(id)) : null, issues.get(id).isEmpty(),
                    issues.get(id)))
                .toList(), page.total(), now);
        }
        SessionDayReader.AttendancePage page =
            reader.attendance(session.id(), query.view().equals("HISTORY"), matching, query.limit(), query.offset());
        Map<UUID, PersonDirectory.Entry> names =
            names(page.attendance().stream().map(Attendance::childId).distinct().toList());
        return new SessionDayPage(page.attendance()
            .stream()
            .map(value -> new SessionDayPage.Child(value.childId(), names.get(value.childId()).name(),
                AttendanceView.from(value), true, collectionIssues(value, now)))
            .toList(), page.total(), now);
    }
    private List<String> collectionIssues(Attendance value, Instant now) {
        if (value.checkedOutAt() != null) {
            return List.of();
        }
        String label = value.labelHash() == null ? "Issue a pickup label before collection."
            : value.labelExpiresAt() == null || !now.isBefore(value.labelExpiresAt())
                ? "Replace the expired pickup label before collection." : "";
        String attempts = value.failedAttempts() > 0 ? "Review unsuccessful pickup-code attempts before handover." : "";
        return Stream.of(label, attempts).filter(issue -> !issue.isEmpty()).toList();
    }
    private Map<UUID, PersonDirectory.Entry> names(List<UUID> ids) {
        return people.getAll(ids).stream().collect(Collectors.toMap(PersonDirectory.Entry::id, Function.identity()));
    }
}
