package org.onechurch.volunteering.infrastructure.persistence;

import static org.onechurch.shared.infrastructure.jooq.Tables.SERVING_MEMBERSHIPS;
import static org.onechurch.shared.infrastructure.jooq.Tables.SERVING_ROLES;
import static org.onechurch.shared.infrastructure.jooq.Tables.SERVING_ROLE_APPROVALS;
import static org.onechurch.shared.infrastructure.jooq.Tables.SERVING_ROLE_REQUIREMENTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.SERVING_TEAMS;

import org.jooq.DSLContext;
import org.jooq.impl.DSL;
import org.onechurch.shared.infrastructure.jooq.tables.records.ServingRolesRecord;
import org.onechurch.volunteering.application.contract.TeamReader;
import org.onechurch.volunteering.domain.RoleApproval;
import org.onechurch.volunteering.domain.ServingRole;
import org.onechurch.volunteering.domain.Team;
import org.onechurch.volunteering.domain.TeamMembership;
import org.onechurch.volunteering.domain.repository.TeamRepository;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.util.List;
import java.util.Optional;
import java.util.UUID;

@Repository
public class JooqTeamStore implements TeamReader, TeamRepository {
    private final DSLContext db;
    public JooqTeamStore(DSLContext db) {
        this.db = db;
    }
    public Optional<Team> team(UUID id) {
        return db.selectFrom(SERVING_TEAMS).where(SERVING_TEAMS.ID.eq(id)).fetchOptional(ServingRecords::team);
    }
    public Optional<Team> lock(UUID id) {
        return db.selectFrom(SERVING_TEAMS)
            .where(SERVING_TEAMS.ID.eq(id))
            .forUpdate()
            .fetchOptional(ServingRecords::team);
    }
    public List<Team> teams(int limit, int offset) {
        return db.selectFrom(SERVING_TEAMS)
            .orderBy(SERVING_TEAMS.NAME, SERVING_TEAMS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(ServingRecords::team);
    }
    public long teamCount() {
        return db.fetchCount(SERVING_TEAMS);
    }
    private ServingRole role(ServingRolesRecord r) {
        return new ServingRole(r.getId(), r.getTeamId(), r.getName(), r.getChildrenRole(), r.getActive(),
            db.select(SERVING_ROLE_REQUIREMENTS.TYPE_ID)
                .from(SERVING_ROLE_REQUIREMENTS)
                .where(SERVING_ROLE_REQUIREMENTS.ROLE_ID.eq(r.getId()))
                .fetch(SERVING_ROLE_REQUIREMENTS.TYPE_ID));
    }
    public Optional<ServingRole> role(UUID id) {
        return db.selectFrom(SERVING_ROLES).where(SERVING_ROLES.ID.eq(id)).fetchOptional(this::role);
    }
    public List<ServingRole> roles(UUID team) {
        return db.selectFrom(SERVING_ROLES)
            .where(SERVING_ROLES.TEAM_ID.eq(team))
            .orderBy(SERVING_ROLES.NAME, SERVING_ROLES.ID)
            .limit(100)
            .fetch(this::role);
    }
    public List<TeamMembership> memberships(UUID team, UUID volunteer) {
        return db.selectFrom(SERVING_MEMBERSHIPS)
            .where(SERVING_MEMBERSHIPS.TEAM_ID.eq(team))
            .and(volunteer == null ? DSL.noCondition() : SERVING_MEMBERSHIPS.VOLUNTEER_ID.eq(volunteer))
            .orderBy(SERVING_MEMBERSHIPS.VALID_FROM.desc())
            .limit(1000)
            .fetch(ServingRecords::teamMembership);
    }
    public List<RoleApproval> approvals(UUID role, UUID volunteer) {
        return db.selectFrom(SERVING_ROLE_APPROVALS)
            .where(SERVING_ROLE_APPROVALS.ROLE_ID.eq(role))
            .and(volunteer == null ? DSL.noCondition() : SERVING_ROLE_APPROVALS.VOLUNTEER_ID.eq(volunteer))
            .orderBy(SERVING_ROLE_APPROVALS.VALID_FROM.desc())
            .limit(1000)
            .fetch(ServingRecords::roleApproval);
    }
    public void addRole(ServingRole role) {
        db.insertInto(SERVING_ROLES)
            .set(SERVING_ROLES.ID, role.id())
            .set(SERVING_ROLES.TEAM_ID, role.teamId())
            .set(SERVING_ROLES.NAME, role.name())
            .set(SERVING_ROLES.CHILDREN_ROLE, role.childrenRole())
            .set(SERVING_ROLES.ACTIVE, role.active())
            .execute();
        for (UUID type : role.requiredCredentials()) {
            db.insertInto(SERVING_ROLE_REQUIREMENTS)
                .set(SERVING_ROLE_REQUIREMENTS.ROLE_ID, role.id())
                .set(SERVING_ROLE_REQUIREMENTS.TYPE_ID, type)
                .execute();
        }
    }
    public void saveRole(ServingRole role) {
        db.update(SERVING_ROLES)
            .set(SERVING_ROLES.NAME, role.name())
            .set(SERVING_ROLES.CHILDREN_ROLE, role.childrenRole())
            .set(SERVING_ROLES.ACTIVE, role.active())
            .where(SERVING_ROLES.ID.eq(role.id()).and(SERVING_ROLES.TEAM_ID.eq(role.teamId())))
            .execute();
        db.deleteFrom(SERVING_ROLE_REQUIREMENTS).where(SERVING_ROLE_REQUIREMENTS.ROLE_ID.eq(role.id())).execute();
        for (UUID type : role.requiredCredentials()) {
            db.insertInto(SERVING_ROLE_REQUIREMENTS)
                .set(SERVING_ROLE_REQUIREMENTS.ROLE_ID, role.id())
                .set(SERVING_ROLE_REQUIREMENTS.TYPE_ID, type)
                .execute();
        }
    }
    public void revokeMembership(UUID id, Instant at) {
        db.update(SERVING_MEMBERSHIPS)
            .set(SERVING_MEMBERSHIPS.REVOKED_AT, ServingRecords.time(at))
            .where(SERVING_MEMBERSHIPS.ID.eq(id))
            .and(SERVING_MEMBERSHIPS.REVOKED_AT.isNull())
            .execute();
    }
    public void revokeApproval(UUID id, Instant at) {
        db.update(SERVING_ROLE_APPROVALS)
            .set(SERVING_ROLE_APPROVALS.REVOKED_AT, ServingRecords.time(at))
            .where(SERVING_ROLE_APPROVALS.ID.eq(id))
            .and(SERVING_ROLE_APPROVALS.REVOKED_AT.isNull())
            .execute();
    }
    public void insert(Team v) {
        db.insertInto(SERVING_TEAMS)
            .set(SERVING_TEAMS.ID, v.id())
            .set(SERVING_TEAMS.NAME, v.name())
            .set(SERVING_TEAMS.ACTIVE, v.active())
            .set(SERVING_TEAMS.VERSION, v.version())
            .execute();
    }
    public void save(Team v) {
        db.update(SERVING_TEAMS)
            .set(SERVING_TEAMS.NAME, v.name())
            .set(SERVING_TEAMS.ACTIVE, v.active())
            .set(SERVING_TEAMS.VERSION, v.version())
            .where(SERVING_TEAMS.ID.eq(v.id()))
            .execute();
    }
    public void addMembership(TeamMembership v) {
        db.insertInto(SERVING_MEMBERSHIPS)
            .set(SERVING_MEMBERSHIPS.ID, v.id())
            .set(SERVING_MEMBERSHIPS.TEAM_ID, v.teamId())
            .set(SERVING_MEMBERSHIPS.VOLUNTEER_ID, v.volunteerId())
            .set(SERVING_MEMBERSHIPS.VALID_FROM, ServingRecords.time(v.validFrom()))
            .set(SERVING_MEMBERSHIPS.VALID_UNTIL, ServingRecords.time(v.validUntil()))
            .set(SERVING_MEMBERSHIPS.REVOKED_AT, ServingRecords.time(v.revokedAt()))
            .execute();
    }
    public void addApproval(RoleApproval v) {
        db.insertInto(SERVING_ROLE_APPROVALS)
            .set(SERVING_ROLE_APPROVALS.ID, v.id())
            .set(SERVING_ROLE_APPROVALS.ROLE_ID, v.roleId())
            .set(SERVING_ROLE_APPROVALS.VOLUNTEER_ID, v.volunteerId())
            .set(SERVING_ROLE_APPROVALS.VALID_FROM, ServingRecords.time(v.validFrom()))
            .set(SERVING_ROLE_APPROVALS.VALID_UNTIL, ServingRecords.time(v.validUntil()))
            .set(SERVING_ROLE_APPROVALS.REVOKED_AT, ServingRecords.time(v.revokedAt()))
            .execute();
    }
}
