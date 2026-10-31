package org.onechurch.volunteering.application;

import org.onechurch.identityaccess.application.contract.AccountDirectory;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.application.UnitOfWork;
import org.onechurch.volunteering.application.contract.RosterReader;
import org.onechurch.volunteering.application.contract.ServingActivityLocks;
import org.onechurch.volunteering.application.contract.ServingAudit;
import org.onechurch.volunteering.application.contract.VolunteerReader;
import org.onechurch.volunteering.domain.AvailabilityException;
import org.onechurch.volunteering.domain.AvailabilityRule;
import org.onechurch.volunteering.domain.Credential;
import org.onechurch.volunteering.domain.CredentialType;
import org.onechurch.volunteering.domain.ServingRules;
import org.onechurch.volunteering.domain.Volunteer;
import org.onechurch.volunteering.domain.VolunteerException;
import org.onechurch.volunteering.domain.repository.VolunteerRepository;

import java.time.Clock;
import java.time.Instant;
import java.time.LocalDate;
import java.time.LocalTime;
import java.util.ArrayList;
import java.util.Comparator;
import java.util.List;
import java.util.UUID;

public final class VolunteerProfiles {
    public record Weekly(int dayOfWeek, LocalTime startsAt, LocalTime endsAt, String timeZone, LocalDate validFrom,
        LocalDate validUntil) {}
    public record ExceptionWindow(Instant startsAt, Instant endsAt, boolean available) {}
    private final VolunteerRepository repository;
    private final VolunteerReader reader;
    private final PersonDirectory people;
    private final AccountDirectory accounts;
    private final UnitOfWork tx;
    private final ServingAudit audit;
    private final Clock clock;
    private final RosterReader roster;
    private final List<ServingActivityLocks> activityLocks;
    public VolunteerProfiles(VolunteerRepository repository, VolunteerReader reader, PersonDirectory people,
        AccountDirectory accounts, UnitOfWork tx, ServingAudit audit, Clock clock, RosterReader roster,
        List<ServingActivityLocks> activityLocks) {
        this.repository = repository;
        this.reader = reader;
        this.people = people;
        this.accounts = accounts;
        this.tx = tx;
        this.audit = audit;
        this.clock = clock;
        this.roster = roster;
        this.activityLocks = List.copyOf(activityLocks);
    }
    public UUID register(UUID actor, UUID person, UUID account) {
        return tx.execute(() -> {
            people.lock(person)
                .filter(PersonDirectory.Entry::available)
                .orElseThrow(() -> VolunteerException.missing("Available person"));
            if (reader.byPerson(person).isPresent()) {
                throw VolunteerException.conflict("This person already has a volunteer profile.");
            }
            if (account != null && !accounts.active(account)) {
                throw VolunteerException.invalid("Choose an active account.");
            }
            Instant now = clock.instant();
            UUID id = UUID.randomUUID();
            repository.insert(new Volunteer(id, person, account, "ACTIVE", 0, 0, now, now));
            audit.record(actor, "volunteer.registered", id, now);
            return id;
        });
    }
    public Volunteer lock(UUID id) {
        return lockAll(List.of(id), List.of()).getFirst();
    }
    public List<Volunteer> lockAll(List<UUID> ids, List<ServingActivityLocks.Activity> extra) {
        List<Volunteer> profiles = ids.stream()
            .distinct()
            .map(id -> reader.volunteer(id).orElseThrow(() -> VolunteerException.missing("Volunteer")))
            .sorted(Comparator.comparing(v -> v.personId().toString()))
            .toList();
        for (Volunteer profile : profiles) {
            people.lock(profile.personId()).orElseThrow(() -> VolunteerException.missing("Person"));
        }
        List<ServingActivityLocks.Activity> affected = new ArrayList<>(roster.volunteerActivities(ids));
        affected.addAll(extra);
        lockActivities(affected);
        return profiles.stream().map(v -> repository.lock(v.id()).orElseThrow()).toList();
    }
    public void lockTeamActivities(UUID team) {
        lockActivities(roster.teamActivities(team));
    }
    private void lockActivities(List<ServingActivityLocks.Activity> activities) {
        List<ServingActivityLocks.Activity> ordered = activities.stream()
            .distinct()
            .sorted(Comparator.comparing((ServingActivityLocks.Activity a) -> a.kind())
                .thenComparing(a -> a.id().toString()))
            .toList();
        for (ServingActivityLocks locks : activityLocks) {
            locks.lock(ordered);
        }
    }
    public void account(UUID actor, UUID id, long expected, UUID account) {
        tx.execute(() -> {
            Volunteer current = lock(id);
            ServingRules.version(current.version(), expected);
            if (account != null && !accounts.active(account)) {
                throw VolunteerException.invalid("Choose an active account.");
            }
            repository.save(new Volunteer(current.id(), current.personId(), account, current.status(),
                current.availabilityRevision(), current.version() + 1, current.createdAt(), clock.instant()));
            audit.record(actor, "volunteer.account.changed", id, clock.instant());
            return null;
        });
    }
    public void status(UUID actor, UUID id, long expected, String status) {
        tx.execute(() -> {
            Volunteer v = lock(id);
            ServingRules.version(v.version(), expected);
            repository.save(v.change(status, clock.instant()));
            audit.record(actor, "volunteer.status", id, clock.instant());
            return null;
        });
    }
    public UUID type(UUID actor, String code, String name) {
        return tx.execute(() -> {
            UUID id = UUID.randomUUID();
            repository.addCredentialType(new CredentialType(id, code, name));
            audit.record(actor, "credential.type.created", id, clock.instant());
            return id;
        });
    }
    public UUID credential(UUID actor, UUID volunteer, long expected, UUID type, Instant from, Instant until,
        String evidence) {
        return tx.execute(() -> {
            Volunteer v = lock(volunteer);
            ServingRules.version(v.version(), expected);
            reader.credentialTypes()
                .stream()
                .filter(t -> t.id().equals(type))
                .findFirst()
                .orElseThrow(() -> VolunteerException.missing("Credential type"));
            UUID id = UUID.randomUUID();
            repository.addCredential(
                new Credential(id, volunteer, type, from, until, evidence, actor, clock.instant(), null));
            repository.save(v.touch(clock.instant()));
            audit.record(actor, "credential.verified", id, clock.instant());
            return id;
        });
    }
    public void revoke(UUID actor, UUID volunteer, long expected, UUID credential, String reason) {
        ServingRules.text(reason, 500);
        tx.execute(() -> {
            Volunteer v = lock(volunteer);
            ServingRules.version(v.version(), expected);
            reader.credentials(volunteer)
                .stream()
                .filter(c -> c.id().equals(credential))
                .findFirst()
                .orElseThrow(() -> VolunteerException.missing("Credential"));
            repository.revokeCredential(credential, clock.instant());
            repository.save(v.touch(clock.instant()));
            audit.record(actor, "credential.revoked", credential, reason, clock.instant());
            return null;
        });
    }
    public void availability(UUID actor, UUID volunteer, long expected, List<Weekly> weekly,
        List<ExceptionWindow> exceptions) {
        if (weekly == null || exceptions == null || weekly.size() > 100 || exceptions.size() > 100) {
            throw VolunteerException.invalid("Use at most 100 weekly rules and 100 exceptions.");
        }
        tx.execute(() -> {
            Volunteer current = lock(volunteer);
            ServingRules.version(current.version(), expected);
            Volunteer changed = current.availability(clock.instant());
            for (Weekly rule : weekly) {
                repository.availability(
                    new AvailabilityRule(UUID.randomUUID(), volunteer, changed.availabilityRevision(), rule.dayOfWeek(),
                        rule.startsAt(), rule.endsAt(), rule.timeZone(), rule.validFrom(), rule.validUntil()));
            }
            for (ExceptionWindow e : exceptions) {
                repository.exception(new AvailabilityException(UUID.randomUUID(), volunteer,
                    changed.availabilityRevision(), e.startsAt(), e.endsAt(), e.available()));
            }
            repository.save(changed);
            audit.record(actor, "volunteer.availability", volunteer, clock.instant());
            return null;
        });
    }
}
