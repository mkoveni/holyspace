package org.onechurch.childrenministry.sessions.infrastructure;

import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.sessions.application.contract.SessionReader;
import org.onechurch.childrenministry.sessions.domain.MinistrySession;
import org.onechurch.childrenministry.sessions.domain.SessionStatus;
import org.onechurch.volunteering.application.contract.ActivitySource;
import org.springframework.stereotype.Component;

import java.time.Instant;
import java.util.List;
import java.util.Optional;
import java.util.UUID;

@Component
public class ServingSessionActivities implements ActivitySource {
    private final SessionReader sessions;
    private final ParticipationReader participation;
    public ServingSessionActivities(SessionReader sessions, ParticipationReader participation) {
        this.sessions = sessions;
        this.participation = participation;
    }
    public String kind() {
        return "CHILDREN_SESSION";
    }
    private Activity view(MinistrySession session) {
        String label = participation.ministryClass(session.classId()).map(c -> c.name()).orElse("Children session");
        return new Activity(session.id(), label, session.startsAt(), session.endsAt(),
            session.status() != SessionStatus.CANCELLED && session.status() != SessionStatus.CLOSED);
    }
    public Optional<Activity> find(UUID id) {
        return sessions.ministrySession(id).map(this::view);
    }
    public List<Activity> upcoming(Instant from, Instant until) {
        return upcoming(from, until, 100, 0);
    }
    public List<Activity> upcoming(Instant from, Instant until, int limit, int offset) {
        return sessions.sessions(null, from, until, limit, offset)
            .stream()
            .map(this::view)
            .filter(Activity::active)
            .toList();
    }
}
