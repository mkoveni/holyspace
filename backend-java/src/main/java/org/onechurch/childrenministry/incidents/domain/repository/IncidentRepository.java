package org.onechurch.childrenministry.incidents.domain.repository;
import org.onechurch.childrenministry.incidents.domain.Incident;
import org.onechurch.childrenministry.incidents.domain.IncidentDetails;
import org.onechurch.childrenministry.incidents.domain.IncidentEvent;

import java.time.LocalDate;
import java.util.Optional;
import java.util.UUID;
public interface IncidentRepository {
    Optional<Incident> lock(UUID id);
    void insert(Incident incident, IncidentDetails details, UUID requestId, String requestHash);
    void update(Incident incident);
    void append(IncidentEvent event);
    int purgeExpired(LocalDate on, int limit);
}
