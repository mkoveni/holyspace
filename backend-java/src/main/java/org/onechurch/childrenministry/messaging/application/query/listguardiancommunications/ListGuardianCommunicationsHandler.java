package org.onechurch.childrenministry.messaging.application.query.listguardiancommunications;
import org.onechurch.childrenministry.messaging.application.contract.DeliveryReader;
import org.onechurch.childrenministry.messaging.application.contract.GuardianCommunicationReader;
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
import java.util.stream.Stream;
public final class ListGuardianCommunicationsHandler {
    public record Row(GuardianCommunicationReader.Summary communication, String childName, String guardianName,
        DeliveryReader.Summary delivery) {}
    public record View(List<Row> communications, long total, long overdue) {}
    private final PermissionChecker auth;
    private final GuardianCommunicationReader reader;
    private final PersonDirectory people;
    private final Clock clock;
    private final ZoneId zone;
    private final DeliveryReader deliveries;
    public ListGuardianCommunicationsHandler(PermissionChecker auth, GuardianCommunicationReader reader,
        PersonDirectory people, Clock clock, ZoneId zone, DeliveryReader deliveries) {
        this.auth = auth;
        this.reader = reader;
        this.people = people;
        this.clock = clock;
        this.zone = zone;
        this.deliveries = deliveries;
    }
    public View handle(ListGuardianCommunicationsQuery q) {
        auth.requirePermission("children.safeguarding");
        ChildrenRules.page(q.limit(), q.offset());
        if (q.status() == null || !List.of("ALL", "DRAFT", "CONTACTED", "CANCELLED").contains(q.status())) {
            throw ChildrenException.invalid("Choose a valid communication status.");
        }
        GuardianCommunicationReader.Page page =
            reader.list(q.childId(), q.status(), clock.instant().atZone(zone).toLocalDate(), q.limit(), q.offset());
        List<UUID> ids =
            page.communications().stream().flatMap(c -> Stream.of(c.childId(), c.guardianId())).distinct().toList();
        Map<UUID, String> names = people.getAll(ids)
            .stream()
            .collect(Collectors.toMap(PersonDirectory.Entry::id, PersonDirectory.Entry::name));
        Map<UUID, DeliveryReader.Summary> outcomes =
            deliveries.summaries(page.communications().stream().map(GuardianCommunicationReader.Summary::id).toList());
        return new View(page.communications()
            .stream()
            .map(c -> new Row(c, names.getOrDefault(c.childId(), "Unavailable person"),
                names.getOrDefault(c.guardianId(), "Unavailable person"), outcomes.get(c.id())))
            .toList(), page.total(), page.overdue());
    }
}
