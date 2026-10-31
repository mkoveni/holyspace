package org.onechurch.childrenministry.incidents.application.query.listincidentguardians;
import org.onechurch.childrenministry.incidents.application.IncidentAccess;
import org.onechurch.childrenministry.incidents.domain.Incident;
import org.onechurch.childrenministry.safeguarding.application.contract.SafeguardingReader;
import org.onechurch.childrenministry.safeguarding.domain.GuardianAuthority;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonDirectory;

import java.time.Clock;
import java.time.Instant;
import java.time.LocalDate;
import java.time.ZoneId;
import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.function.Function;
import java.util.stream.Collectors;
public final class ListIncidentGuardiansHandler {
    public record Guardian(UUID authorityId, UUID guardianId, String name) {}
    private final PermissionChecker auth;
    private final IncidentAccess access;
    private final SafeguardingReader safeguarding;
    private final PersonDirectory people;
    private final Clock clock;
    private final ZoneId zone;
    public ListIncidentGuardiansHandler(PermissionChecker auth, IncidentAccess access, SafeguardingReader safeguarding,
        PersonDirectory people, Clock clock, ZoneId zone) {
        this.auth = auth;
        this.access = access;
        this.safeguarding = safeguarding;
        this.people = people;
        this.clock = clock;
        this.zone = zone;
    }
    public List<Guardian> handle(ListIncidentGuardiansQuery q) {
        auth.requirePermission("children.incidents.manage");
        Instant now = clock.instant();
        LocalDate on = now.atZone(zone).toLocalDate();
        Incident incident = access.current(q.incidentId(), on);
        List<GuardianAuthority> authorities =
            safeguarding.authorities(incident.childId()).stream().filter(a -> a.covers(now, now)).toList();
        Map<UUID, PersonDirectory.Entry> names =
            people.getAll(authorities.stream().map(a -> a.guardianId()).distinct().toList())
                .stream()
                .collect(Collectors.toMap(PersonDirectory.Entry::id, Function.identity()));
        return authorities.stream().filter(a -> {
            PersonDirectory.Entry p = names.get(a.guardianId());
            return p != null && p.available() && p.birthDate() != null && !p.birthDate().plusYears(18).isAfter(on);
        }).map(a -> new Guardian(a.id(), a.guardianId(), names.get(a.guardianId()).name())).toList();
    }
}
