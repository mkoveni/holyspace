package org.onechurch.childrenministry.sessions.application.command.applyteamplan;
import org.onechurch.childrenministry.sessions.application.TeamPlanDraft;

import java.util.UUID;
public record ApplyTeamPlanCommand(UUID actorId, TeamPlanDraft draft, String reason) {}
