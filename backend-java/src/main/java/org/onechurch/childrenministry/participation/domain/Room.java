package org.onechurch.childrenministry.participation.domain;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;

import java.util.UUID;
public record Room(UUID id, UUID locationId, String name, int capacity) {
    public Room {
        name = ChildrenRules.text(name, 120);
        if (capacity < 1) {
            throw ChildrenException.invalid("Room capacity must be positive.");
        }
    }

}
