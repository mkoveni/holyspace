package org.onechurch.childrenministry.sessions.application;
import org.onechurch.childrenministry.sessions.application.contract.TeamPlanningReader;
import org.onechurch.childrenministry.sessions.domain.CapacityPolicy;
import org.onechurch.childrenministry.sessions.domain.MinistrySession;
import org.onechurch.childrenministry.sessions.domain.SessionStaff;
import org.onechurch.childrenministry.sessions.domain.SessionStatus;
import org.onechurch.childrenministry.sessions.domain.Worker;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.AccountDirectory;
import org.onechurch.people.application.contract.PersonDirectory;

import java.time.Clock;
import java.time.Instant;
import java.time.LocalDate;
import java.time.ZoneId;
import java.time.temporal.ChronoUnit;
import java.util.ArrayList;
import java.util.Collection;
import java.util.HashMap;
import java.util.List;
import java.util.Locale;
import java.util.Map;
import java.util.Set;
import java.util.UUID;
import java.util.function.Function;
import java.util.stream.Collectors;
public final class TeamPlanning {
    private final TeamPlanningReader reader;
    private final PersonDirectory people;
    private final AccountDirectory accounts;
    private final Clock clock;
    private final ZoneId zone;
    public TeamPlanning(TeamPlanningReader reader, PersonDirectory people, AccountDirectory accounts, Clock clock,
        ZoneId zone) {
        this.reader = reader;
        this.people = people;
        this.accounts = accounts;
        this.clock = clock;
        this.zone = zone;
    }
    public TeamPlanningView list(LocalDate from, LocalDate until, UUID programmeId, UUID locationId, String search,
        String state, int limit, int offset) {
        ChildrenRules.page(limit, offset);
        if (from == null || until == null || until.isBefore(from) || ChronoUnit.DAYS.between(from, until) > 89) {
            throw ChildrenException.invalid("Choose up to 90 days for team planning.");
        }
        if (state == null || !List.of("ALL", "READY", "ATTENTION").contains(state)) {
            throw ChildrenException.invalid("Choose a valid readiness view.");
        }
        Instant now = clock.instant();
        List<TeamPlanningReader.Session> selected = reader.upcoming(from.atStartOfDay(zone).toInstant(),
            until.plusDays(1).atStartOfDay(zone).toInstant(), programmeId, locationId, now);
        if (selected.size() > 1000) {
            throw ChildrenException.invalid("More than 1,000 sessions match. Narrow the dates or programme.");
        }
        String term = ChildrenRules.optional(search, 120).toLowerCase(Locale.ROOT);
        List<TeamPlanningView.Session> all = assess(selected, reader.facts(selected, Set.of()), null, now).stream()
            .filter(row -> (row.programme() + " " + row.ministryClass() + " " + row.room() + " "
                + row.team().stream().map(TeamPlanningView.Staff::name).collect(Collectors.joining(" ")))
                .toLowerCase(Locale.ROOT)
                .contains(term))
            .toList();
        List<TeamPlanningView.Session> filtered =
            all.stream().filter(row -> state.equals("ALL") || (state.equals("READY") == row.ready())).toList();
        TeamPlanningView.Summary summary =
            new TeamPlanningView.Summary(all.size(), all.stream().filter(TeamPlanningView.Session::ready).count(),
                all.stream().filter(row -> !row.ready()).count(),
                all.stream().filter(row -> !row.qualifiedLeader()).count(),
                all.stream().filter(row -> row.plannedCapacity() < row.session().seatLimit()).count(),
                all.stream().filter(row -> row.team().stream().anyMatch(TeamPlanningView.Staff::clash)).count());
        return new TeamPlanningView(filtered.stream().skip(offset).limit(limit).toList(), filtered.size(), summary,
            zone.getId(), now);
    }
    public TeamPlanningView.Workers workers(String search, int limit, int offset) {
        ChildrenRules.page(limit, offset);
        String term = ChildrenRules.optional(search, 120);
        TeamPlanningReader.Workers page =
            reader.workers(term.isEmpty() ? null : people.matchingIds(term), limit, offset);
        Map<UUID, PersonDirectory.Entry> names = names(page.values().stream().map(Worker::personId).toList());
        return new TeamPlanningView.Workers(page.values()
            .stream()
            .map(worker -> new TeamPlanningView.WorkerOption(worker.personId(),
                names.containsKey(worker.personId()) ? names.get(worker.personId()).name() : "Unavailable person",
                worker.screenedUntil(), worker.trainedUntil(), worker.revokedAt() != null))
            .toList(), page.total());
    }
    public TeamPlanningView.Preview preview(TeamPlanDraft draft) {
        validate(draft);
        Instant now = clock.instant();
        List<TeamPlanningReader.Session> selected =
            reader.sessions(draft.targets().stream().map(TeamPlanDraft.Target::sessionId).toList());
        if (selected.size() != draft.targets().size()) {
            throw ChildrenException.missing("Session");
        }
        TeamPlanningReader.Facts facts = reader.facts(selected, Set.of(draft.workerId()));
        if (facts.workers().stream().noneMatch(worker -> worker.personId().equals(draft.workerId()))) {
            throw ChildrenException.missing("Worker");
        }
        List<String> blockers = new ArrayList<>();
        Map<UUID, Long> versions = draft.targets()
            .stream()
            .collect(Collectors.toMap(TeamPlanDraft.Target::sessionId, TeamPlanDraft.Target::version));
        for (TeamPlanningReader.Session row : selected) {
            MinistrySession session = row.value();
            if (session.version() != versions.get(session.id())) {
                blockers.add("A selected session changed. Reload the planning workspace.");
            }
            if (session.status() != SessionStatus.PLANNED || !session.startsAt().isAfter(now)) {
                blockers.add("Only future planned sessions can be changed in bulk.");
            }
            List<SessionStaff> team =
                facts.staff().stream().filter(member -> member.sessionId().equals(session.id())).toList();
            if (draft.replaceWorkerId() != null
                && team.stream().noneMatch(member -> member.workerId().equals(draft.replaceWorkerId()))) {
                blockers.add("The person to replace is not assigned to every selected session.");
            }
            if (team.stream()
                .anyMatch(member -> member.present() && (member.workerId().equals(draft.workerId())
                    || member.workerId().equals(draft.replaceWorkerId())))) {
                blockers.add("Confirmed presence must be managed on the individual session.");
            }
        }
        List<TeamPlanningView.Session> projected = assess(selected, facts, draft, now);
        if (projected.stream()
            .anyMatch(row -> row.team()
                .stream()
                .anyMatch(member -> member.workerId().equals(draft.workerId()) && member.clash()))) {
            blockers.add(
                "The selected worker has an overlapping assignment. Choose different sessions or resolve the clash first.");
        }
        return new TeamPlanningView.Preview(projected, blockers.stream().distinct().toList(), now);
    }
    public void validate(TeamPlanDraft draft) {
        if (draft == null || draft.targets() == null || draft.targets().isEmpty() || draft.targets().size() > 25
            || draft.workerId() == null || draft.role() == null
            || draft.targets()
                .stream()
                .anyMatch(target -> target == null || target.sessionId() == null || target.version() < 0)
            || draft.targets().stream().map(TeamPlanDraft.Target::sessionId).distinct().count() != draft.targets()
                .size()
            || draft.workerId().equals(draft.replaceWorkerId())) {
            throw ChildrenException.invalid(
                "Choose 1–25 distinct sessions, a registered worker, a role and a different person to replace.");
        }
    }
    private List<TeamPlanningView.Session> assess(List<TeamPlanningReader.Session> selected,
        TeamPlanningReader.Facts facts, TeamPlanDraft draft, Instant now) {
        Map<UUID, List<SessionStaff>> team =
            new HashMap<>(facts.staff().stream().collect(Collectors.groupingBy(SessionStaff::sessionId)));
        Set<UUID> ids = selected.stream().map(row -> row.value().id()).collect(Collectors.toSet());
        if (draft != null) {
            selected.forEach(row -> {
                List<SessionStaff> changed = new ArrayList<>(team.getOrDefault(row.value().id(), List.of())
                    .stream()
                    .filter(member -> !member.workerId().equals(draft.workerId())
                        && !member.workerId().equals(draft.replaceWorkerId()))
                    .toList());
                changed.add(new SessionStaff(row.value().id(), draft.workerId(), draft.role(), false));
                team.put(row.value().id(), List.copyOf(changed));
            });
        }
        List<TeamPlanningReader.Booking> bookings =
            new ArrayList<>(facts.bookings().stream().filter(booking -> !ids.contains(booking.sessionId())).toList());
        selected.forEach(row -> team.getOrDefault(row.value().id(), List.of())
            .forEach(member -> bookings.add(new TeamPlanningReader.Booking(row.value().id(), member.workerId(),
                row.value().startsAt(), row.value().endsAt()))));
        Map<UUID, List<TeamPlanningReader.Booking>> busy =
            bookings.stream().collect(Collectors.groupingBy(TeamPlanningReader.Booking::workerId));
        Map<UUID, Worker> workers =
            facts.workers().stream().collect(Collectors.toMap(Worker::personId, Function.identity()));
        Map<UUID, PersonDirectory.Entry> names = names(workers.keySet());
        Map<UUID, Boolean> active = facts.workers()
            .stream()
            .collect(Collectors.toMap(Worker::personId, worker -> accounts.active(worker.accountId())));
        return selected.stream().map(row -> {
            MinistrySession session = row.value();
            LocalDate on = session.startsAt().atZone(ZoneId.of(row.timeZone())).toLocalDate();
            List<TeamPlanningView.Staff> staff = team.getOrDefault(session.id(), List.of()).stream().map(member -> {
                Worker worker = workers.get(member.workerId());
                PersonDirectory.Entry person = names.get(member.workerId());
                List<String> issues = new ArrayList<>();
                if (person == null || !person.available() || person.birthDate() == null
                    || person.birthDate().plusYears(18).isAfter(on)) {
                    issues.add("Review the person's availability and adult identity.");
                }
                if (worker == null || !active.getOrDefault(member.workerId(), false)) {
                    issues.add("The linked worker account is unavailable.");
                }
                if (worker != null && !worker.eligible(now, session.endsAt())) {
                    issues.add("Screening or training does not cover this session, or clearance is revoked.");
                }
                boolean qualified = issues.isEmpty();
                boolean clash = busy.getOrDefault(member.workerId(), List.of())
                    .stream()
                    .anyMatch(booking -> !booking.sessionId().equals(session.id())
                        && booking.startsAt().isBefore(session.endsAt())
                        && booking.endsAt().isAfter(session.startsAt()));
                if (clash) {
                    issues.add("Assigned to an overlapping session.");
                }
                return new TeamPlanningView.Staff(member.workerId(),
                    person == null ? "Unavailable person" : person.name(), member.role().name(), member.present(),
                    qualified, clash, worker == null ? null : worker.screenedUntil(),
                    worker == null ? null : worker.trainedUntil(), List.copyOf(issues));
            }).toList();
            int qualified = (int) staff.stream().filter(member -> member.qualified() && !member.clash()).count();
            boolean leader = staff.stream()
                .anyMatch(member -> member.qualified() && !member.clash() && member.role().equals("LEADER"));
            int capacity = CapacityPolicy.capacity(session.seatLimit(), row.roomCapacity(), qualified, leader,
                row.minimumStaff(), row.childrenPerStaff());
            List<String> issues = new ArrayList<>();
            if (!leader) {
                issues.add("A qualified, available leader is needed.");
            }
            if (capacity < session.seatLimit()) {
                issues.add("The planned team supports " + capacity + " of " + session.seatLimit() + " seats.");
            }
            staff.forEach(member -> member.issues().forEach(issue -> issues.add(member.name() + ": " + issue)));
            int required = Math.max(row.minimumStaff(),
                (session.seatLimit() + row.childrenPerStaff() - 1) / row.childrenPerStaff());
            return new TeamPlanningView.Session(session, row.programmeId(), row.programme(), row.ministryClass(),
                row.room(), row.timeZone(), required, qualified, leader, capacity, issues.isEmpty(),
                session.status() == SessionStatus.PLANNED && session.startsAt().isAfter(now), List.copyOf(issues),
                staff);
        }).toList();
    }
    private Map<UUID, PersonDirectory.Entry> names(Collection<UUID> ids) {
        return people.getAll(ids).stream().collect(Collectors.toMap(PersonDirectory.Entry::id, Function.identity()));
    }
}
