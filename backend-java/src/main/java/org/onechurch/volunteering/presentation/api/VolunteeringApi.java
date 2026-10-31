package org.onechurch.volunteering.presentation.api;

import org.onechurch.volunteering.application.VolunteerProfiles;

import io.swagger.v3.oas.annotations.media.Schema;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

import java.time.Instant;
import java.util.List;
import java.util.UUID;

@Tag(name = "Volunteers and serving teams")
public interface VolunteeringApi {
    @Schema(name = "ServingVolunteeringCreated")
    record Created(UUID id) {}
    @Schema(name = "ServingVolunteeringRegister")
    record Register(@NotNull UUID personId, UUID accountId) {}
    @Schema(name = "ServingVolunteeringStatus")
    record Status(@Min(0) long expectedVersion, @NotBlank String status) {}
    @Schema(name = "ServingVolunteeringTeamDraft")
    record TeamDraft(@NotBlank @Size(max = 150) String name) {}
    @Schema(name = "ServingVolunteeringTeamEdit")
    record TeamEdit(@Min(0) long expectedVersion, @NotBlank @Size(max = 150) String name, boolean active) {}
    @Schema(name = "ServingVolunteeringRole")
    record Role(@Min(0) long expectedVersion, @NotBlank @Size(max = 100) String name, @NotBlank String childrenRole,
        @NotNull @Size(max = 30) List<UUID> requiredCredentials) {}
    @Schema(name = "ServingVolunteeringMembership")
    record Membership(@Min(0) long expectedVersion, @NotNull UUID volunteerId, @NotNull Instant validFrom,
        @NotNull Instant validUntil) {}
    @Schema(name = "ServingVolunteeringApproval")
    record Approval(@Min(0) long expectedVersion, @NotNull UUID volunteerId, @NotNull UUID roleId,
        @NotNull Instant validFrom, @NotNull Instant validUntil) {}
    @Schema(name = "ServingVolunteeringRevoke")
    record Revoke(@Min(0) long expectedVersion, @NotNull UUID volunteerId, @NotNull UUID recordId,
        boolean roleApproval) {}
    @Schema(name = "ServingVolunteeringCredentialTypeDraft")
    record CredentialTypeDraft(@NotBlank @Size(max = 80) String code, @NotBlank @Size(max = 150) String name) {}
    @Schema(name = "ServingVolunteeringCredentialDraft")
    record CredentialDraft(@Min(0) long expectedVersion, @NotNull UUID typeId, @NotNull Instant validFrom,
        @NotNull Instant validUntil, @NotBlank @Size(max = 500) String evidence) {}
    @Schema(name = "ServingVolunteeringCredentialRevoke")
    record CredentialRevoke(@Min(0) long expectedVersion, @NotNull UUID credentialId,
        @NotBlank @Size(max = 500) String reason) {}
    @Schema(name = "ServingVolunteeringAvailability")
    record Availability(@Min(0) long expectedVersion, @NotNull @Size(max = 100) List<VolunteerProfiles.Weekly> weekly,
        @NotNull @Size(max = 100) List<VolunteerProfiles.ExceptionWindow> exceptions) {}
    @Schema(name = "ServingVolunteeringShiftEdit")
    record ShiftEdit(@Min(0) long expectedVersion, @NotNull Instant startsAt, @NotNull Instant endsAt, int positions,
        @NotBlank String status) {}
    @Schema(name = "ServingVolunteeringAssign")
    record Assign(@NotNull UUID requestId, @NotNull UUID shiftId, @Min(0) long expectedVersion,
        @NotNull UUID volunteerId, @NotBlank String status) {}
    @Schema(name = "ServingVolunteeringAssignmentEdit")
    record AssignmentEdit(@Min(0) long expectedVersion, @NotBlank String status,
        @NotBlank @Size(max = 500) String reason) {}
    @Schema(name = "ServingVolunteeringInvite")
    record Invite(@Min(0) long expectedVersion, @NotNull Instant expiresAt) {}
    @Schema(name = "ServingVolunteeringRespond")
    record Respond(@Min(0) long expectedVersion, @NotBlank String decision) {}
    @Schema(name = "ServingVolunteeringExpire")
    record Expire(int limit) {}
}
