package org.onechurch.childrenministry.incidents.application.query.listincidents;
import org.onechurch.childrenministry.incidents.application.contract.IncidentReader;
import org.onechurch.childrenministry.incidents.domain.Incident;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonDirectory;

import java.time.Clock;
import java.time.ZoneId;
import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.stream.Collectors;
public final class ListIncidentsHandler {
    public record Row(Incident incident, String childName) {}
    public record View(List<Row> incidents, long total, long overdue, long unassigned) {}
    private final PermissionChecker auth;
    private final IncidentReader reader;
    private final PersonDirectory people;
    private final Clock clock;
    private final ZoneId zone;
    public ListIncidentsHandler(PermissionChecker auth, IncidentReader reader, PersonDirectory people, Clock clock,
        ZoneId zone) {
        this.auth = auth;
        this.reader = reader;
        this.people = people;
        this.clock = clock;
        this.zone = zone;
    }
    public View handle(ListIncidentsQuery q) {
        auth.requirePermission("children.incidents.manage");
        ChildrenRules.page(q.limit(), q.offset());
        if (q.status() == null || !List.of("ALL", "ACTIVE", "OPEN", "IN_REVIEW", "CLOSED").contains(q.status())) {
            throw ChildrenException.invalid("Choose a valid incident status.");
        }
        IncidentReader.Page page = reader.list(q.sessionId(), q.childId(), q.status(), q.overdueOnly(),
            clock.instant().atZone(zone).toLocalDate(), q.limit(), q.offset());
        Map<UUID, String> names = people.getAll(page.incidents().stream().map(Incident::childId).distinct().toList())
            .stream()
            .collect(Collectors.toMap(PersonDirectory.Entry::id, PersonDirectory.Entry::name));
        return new View(page.incidents()
            .stream()
            .map(i -> new Row(i, names.getOrDefault(i.childId(), "Unavailable person")))
            .toList(), page.total(), page.overdue(), page.unassigned());
    }
}
