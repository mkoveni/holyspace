package org.onechurch.volunteering.application;

import org.onechurch.shared.application.UnitOfWork;
import org.onechurch.volunteering.application.contract.ServingAudit;
import org.onechurch.volunteering.application.contract.TeamReader;
import org.onechurch.volunteering.application.contract.VolunteerReader;
import org.onechurch.volunteering.domain.CredentialType;
import org.onechurch.volunteering.domain.RoleApproval;
import org.onechurch.volunteering.domain.ServingRole;
import org.onechurch.volunteering.domain.ServingRules;
import org.onechurch.volunteering.domain.Team;
import org.onechurch.volunteering.domain.TeamMembership;
import org.onechurch.volunteering.domain.VolunteerException;
import org.onechurch.volunteering.domain.repository.TeamRepository;

import java.time.Clock;
import java.time.Instant;
import java.util.List;
import java.util.UUID;

public final class TeamManagement {
    private final TeamRepository repository;
    private final TeamReader teams;
    private final VolunteerReader volunteers;
    private final VolunteerProfiles profiles;
    private final UnitOfWork tx;
    private final ServingAudit audit;
    private final Clock clock;
    public TeamManagement(TeamRepository repository, TeamReader teams, VolunteerReader volunteers,
        VolunteerProfiles profiles, UnitOfWork tx, ServingAudit audit, Clock clock) {
        this.repository = repository;
        this.teams = teams;
        this.volunteers = volunteers;
        this.profiles = profiles;
        this.tx = tx;
        this.audit = audit;
        this.clock = clock;
    }
    public UUID create(UUID actor, String name) {
        return tx.execute(() -> {
            UUID id = UUID.randomUUID();
            repository.insert(new Team(id, name, true, 0));
            audit.record(actor, "team.created", id, clock.instant());
            return id;
        });
    }
    private Team lock(UUID id, long expected) {
        Team team = repository.lock(id).orElseThrow(() -> VolunteerException.missing("Team"));
        ServingRules.version(team.version(), expected);
        return team;
    }
    public void update(UUID actor, UUID team, long expected, String name, boolean active) {
        tx.execute(() -> {
            profiles.lockTeamActivities(team);
            Team current = lock(team, expected);
            repository.save(current.change(name, active));
            audit.record(actor, "team.changed", team, clock.instant());
            return null;
        });
    }
    public UUID role(UUID actor, UUID team, long expected, String name, String childrenRole, List<UUID> requirements) {
        return tx.execute(() -> {
            profiles.lockTeamActivities(team);
            Team current = lock(team, expected);
            if (requirements == null) {
                throw VolunteerException.invalid("Choose credential requirements from the catalogue.");
            }
            for (UUID type : requirements) {
                volunteers.credentialTypes()
                    .stream()
                    .filter(t -> t.id().equals(type))
                    .findFirst()
                    .orElseThrow(() -> VolunteerException.missing("Credential type"));
            }
            UUID id = UUID.randomUUID();
            repository.addRole(new ServingRole(id, team, name, childrenRole, true, requirements));
            repository.save(current.touch());
            audit.record(actor, "team.role.created", id, clock.instant());
            return id;
        });
    }
    public void updateRole(UUID actor, UUID team, UUID roleId, long expected, String name, String childrenRole,
        boolean active, List<UUID> requirements) {
        tx.execute(() -> {
            profiles.lockTeamActivities(team);
            Team current = lock(team, expected);
            ServingRole existing = teams.role(roleId)
                .filter(role -> role.teamId().equals(team))
                .orElseThrow(() -> VolunteerException.missing("Team role"));
            if (!existing.childrenRole().equals(childrenRole)) {
                throw VolunteerException.conflict(
                    "Create a separate role for a different children responsibility. Existing approvals must retain their scope.");
            }
            ServingRole changed = new ServingRole(roleId, team, name, childrenRole, active, requirements);
            List<UUID> known = volunteers.credentialTypes().stream().map(CredentialType::id).toList();
            if (!known.containsAll(requirements)) {
                throw VolunteerException.invalid("Choose known credential types.");
            }
            repository.saveRole(changed);
            repository.save(current.touch());
            audit.record(actor, "team.role.changed", roleId, clock.instant());
            return null;
        });
    }
    public UUID membership(UUID actor, UUID team, long expected, UUID volunteer, Instant from, Instant until) {
        return tx.execute(() -> {
            profiles.lock(volunteer);
            Team current = lock(team, expected);
            if (!current.active()) {
                throw VolunteerException.conflict("The team is inactive.");
            }
            TeamMembership membership = new TeamMembership(UUID.randomUUID(), team, volunteer, from, until, null);
            if (teams.memberships(team, volunteer)
                .stream()
                .anyMatch(m -> m.revokedAt() == null && ServingRules.overlaps(m.validFrom(), m.validUntil(),
                    membership.validFrom(), membership.validUntil()))) {
                throw VolunteerException.conflict("A team membership already covers part of this interval.");
            }
            repository.addMembership(membership);
            repository.save(current.touch());
            audit.record(actor, "team.member.added", membership.id(), clock.instant());
            return membership.id();
        });
    }
    public UUID approval(UUID actor, UUID team, long expected, UUID volunteer, UUID role, Instant from, Instant until) {
        return tx.execute(() -> {
            profiles.lock(volunteer);
            Team current = lock(team, expected);
            teams.role(role)
                .filter(r -> r.teamId().equals(team))
                .orElseThrow(() -> VolunteerException.missing("Team role"));
            RoleApproval approval = new RoleApproval(UUID.randomUUID(), role, volunteer, from, until, null);
            if (teams.approvals(role, volunteer)
                .stream()
                .anyMatch(a -> a.revokedAt() == null && ServingRules.overlaps(a.validFrom(), a.validUntil(),
                    approval.validFrom(), approval.validUntil()))) {
                throw VolunteerException.conflict("A role approval already covers part of this interval.");
            }
            repository.addApproval(approval);
            repository.save(current.touch());
            audit.record(actor, "team.role.approved", approval.id(), clock.instant());
            return approval.id();
        });
    }
    public void revoke(UUID actor, UUID team, long expected, UUID volunteer, UUID record, boolean approval) {
        tx.execute(() -> {
            profiles.lock(volunteer);
            Team current = lock(team, expected);
            if (approval) {
                boolean found = teams.roles(team)
                    .stream()
                    .flatMap(r -> teams.approvals(r.id(), volunteer).stream())
                    .anyMatch(a -> a.id().equals(record));
                if (!found) {
                    throw VolunteerException.missing("Role approval");
                }
                repository.revokeApproval(record, clock.instant());
            } else {
                teams.memberships(team, volunteer)
                    .stream()
                    .filter(m -> m.id().equals(record))
                    .findFirst()
                    .orElseThrow(() -> VolunteerException.missing("Membership"));
                repository.revokeMembership(record, clock.instant());
            }
            repository.save(current.touch());
            audit.record(actor, approval ? "team.approval.revoked" : "team.membership.revoked", record,
                clock.instant());
            return null;
        });
    }
}
