package org.onechurch.volunteering.application.command.respondmyvolunteerinvitation;
import java.util.UUID;
public record RespondMyVolunteerInvitationCommand(UUID invitationId, long expectedVersion, String decision) {}
