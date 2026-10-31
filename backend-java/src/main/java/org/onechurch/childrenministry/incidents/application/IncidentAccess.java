package org.onechurch.childrenministry.incidents.application;
import org.onechurch.childrenministry.incidents.application.contract.IncidentReader;
import org.onechurch.childrenministry.incidents.domain.Incident;
import org.onechurch.childrenministry.shared.domain.ChildrenException;

import java.time.LocalDate;
import java.util.UUID;
public final class IncidentAccess {
    private final IncidentReader reader;
    public IncidentAccess(IncidentReader reader) {
        this.reader = reader;
    }
    public Incident current(UUID id, LocalDate on) {
        return reader.find(id)
            .filter(value -> !value.retainUntil().isBefore(on))
            .orElseThrow(() -> ChildrenException.missing("Current incident"));
    }
}
