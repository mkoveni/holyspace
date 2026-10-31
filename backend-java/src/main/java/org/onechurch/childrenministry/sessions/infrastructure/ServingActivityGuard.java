package org.onechurch.childrenministry.sessions.infrastructure;

import org.onechurch.childrenministry.sessions.domain.repository.SessionRepository;
import org.onechurch.volunteering.application.contract.ServingActivityLocks;
import org.springframework.stereotype.Component;

import java.util.Comparator;
import java.util.List;
import java.util.UUID;

@Component
public final class ServingActivityGuard implements ServingActivityLocks {
    private final SessionRepository sessions;
    public ServingActivityGuard(SessionRepository sessions) {
        this.sessions = sessions;
    }
    public void lock(List<Activity> activities) {
        activities.stream()
            .filter(a -> a.kind().equals("CHILDREN_SESSION"))
            .map(Activity::id)
            .distinct()
            .sorted(Comparator.comparing(UUID::toString))
            .forEach(sessions::ministrySessionForUpdate);
    }
}
