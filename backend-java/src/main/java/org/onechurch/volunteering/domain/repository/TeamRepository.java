package org.onechurch.volunteering.domain.repository;

import org.onechurch.volunteering.domain.RoleApproval;
import org.onechurch.volunteering.domain.ServingRole;
import org.onechurch.volunteering.domain.Team;
import org.onechurch.volunteering.domain.TeamMembership;

import java.time.Instant;
import java.util.Optional;
import java.util.UUID;

public interface TeamRepository {
    Optional<Team> lock(UUID id);
    void insert(Team team);
    void save(Team team);
    void addRole(ServingRole role);
    void saveRole(ServingRole role);
    void addMembership(TeamMembership membership);
    void addApproval(RoleApproval approval);
    void revokeMembership(UUID id, Instant at);
    void revokeApproval(UUID id, Instant at);
}
