package org.onechurch.childrenministry.incidents.application.query.listincidentattendance;
import org.onechurch.childrenministry.incidents.application.contract.IncidentReader;
import org.onechurch.childrenministry.sessions.application.StaffScope;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonDirectory;

import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.stream.Collectors;
public final class ListIncidentAttendanceHandler {
    public record Row(IncidentReader.Visit visit, String childName) {}
    public record View(List<Row> visits, long total) {}
    private final PermissionChecker auth;
    private final StaffScope scope;
    private final IncidentReader reader;
    private final PersonDirectory people;
    public ListIncidentAttendanceHandler(PermissionChecker auth, StaffScope scope, IncidentReader reader,
        PersonDirectory people) {
        this.auth = auth;
        this.scope = scope;
        this.reader = reader;
        this.people = people;
    }
    public View handle(ListIncidentAttendanceQuery q) {
        auth.requirePermission(q.actorId(), auth.hasPermission("children.incidents.manage")
            ? "children.incidents.manage" : "children.incidents.record");
        ChildrenRules.page(q.limit(), q.offset());
        if (!auth.hasPermission("children.incidents.manage"))
            scope.require(q.actorId(), q.sessionId());
        String term = ChildrenRules.optional(q.search(), 120);
        IncidentReader.Visits page =
            reader.visits(q.sessionId(), term.isEmpty() ? null : people.matchingIds(term), q.limit(), q.offset());
        Map<UUID, String> names =
            people.getAll(page.visits().stream().map(IncidentReader.Visit::childId).distinct().toList())
                .stream()
                .collect(Collectors.toMap(PersonDirectory.Entry::id, PersonDirectory.Entry::name));
        return new View(
            page.visits().stream().map(v -> new Row(v, names.getOrDefault(v.childId(), "Unavailable person"))).toList(),
            page.total());
    }
}
