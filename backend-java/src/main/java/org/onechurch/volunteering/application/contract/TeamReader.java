package org.onechurch.volunteering.application.contract;

import org.onechurch.volunteering.domain.RoleApproval;
import org.onechurch.volunteering.domain.ServingRole;
import org.onechurch.volunteering.domain.Team;
import org.onechurch.volunteering.domain.TeamMembership;

import java.util.List;
import java.util.Optional;
import java.util.UUID;

public interface TeamReader {
    Optional<Team> team(UUID id);
    List<Team> teams(int limit, int offset);
    long teamCount();
    Optional<ServingRole> role(UUID id);
    List<ServingRole> roles(UUID team);
    List<TeamMembership> memberships(UUID team, UUID volunteer);
    List<RoleApproval> approvals(UUID role, UUID volunteer);
}
