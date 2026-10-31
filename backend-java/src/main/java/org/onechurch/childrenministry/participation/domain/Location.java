package org.onechurch.childrenministry.participation.domain;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;

import java.time.ZoneId;
import java.util.UUID;
public record Location(UUID id, String name, String timeZone) {
    public Location {
        name = ChildrenRules.text(name, 120);
        ZoneId.of(timeZone);
    }

}
