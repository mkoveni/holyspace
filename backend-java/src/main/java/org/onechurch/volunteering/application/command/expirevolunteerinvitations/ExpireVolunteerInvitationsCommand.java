package org.onechurch.volunteering.application.command.expirevolunteerinvitations;

import java.util.UUID;

public record ExpireVolunteerInvitationsCommand(UUID actorId, int limit) {}
