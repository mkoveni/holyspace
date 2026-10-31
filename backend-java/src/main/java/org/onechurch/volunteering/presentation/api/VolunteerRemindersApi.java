package org.onechurch.volunteering.presentation.api;

import org.onechurch.communications.application.contract.DeliveryReader;
import org.onechurch.people.application.contract.PersonEmailDirectory;
import org.onechurch.volunteering.application.ReminderReview;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestParam;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.media.Schema;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

import java.util.List;
import java.util.UUID;

@Tag(name = "Volunteer reminders")
public interface VolunteerRemindersApi {
    @Schema(name = "ServingVolunteerRemindersDraft")
    record Draft(@NotNull UUID contactId, @NotBlank @Size(max = 2000) String message) {}
    @Schema(name = "ServingVolunteerRemindersApproval")
    record Approval(@NotNull UUID requestId, @NotNull UUID contactId, @NotBlank @Size(max = 2000) String message,
        @NotBlank String reviewHash, @NotBlank @Size(max = 500) String verificationEvidence) {
        @Override
        public String toString() {
            return "Approval[requestId=" + requestId + "]";
        }
    }
    @GetMapping("/api/volunteering/assignments/{id}/reminder-contacts")
    @Operation(summary = "Choose a current People email", description = "Requires volunteering.communications.send. Confirm recipient identity and communication permission before approval.")
    List<PersonEmailDirectory.Email> contacts(@PathVariable UUID id);
    @PostMapping("/api/volunteering/assignments/{id}/reminder-review")
    @Operation(summary = "Review a rendered reminder", description = "Read-only. Requires volunteering.communications.send. Uses the published Communications template, escaped variables and a hash binding the current assignment, duty and recipient.")
    ReminderReview.Review review(@PathVariable UUID id, @Valid @RequestBody Draft body);
    @PostMapping("/api/volunteering/assignments/{id}/reminders")
    @Operation(summary = "Approve and queue the reviewed reminder", description = "Requires volunteering.communications.send and recipient verification evidence. Freezes rendered HTML. Dispatch rechecks the assignment, current contact and approver access. Unknown transport outcomes are never automatically retried.")
    UUID approve(@PathVariable UUID id, @Valid @RequestBody Approval body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @GetMapping("/api/volunteering/deliveries")
    @Operation(summary = "Track volunteer reminder deliveries", description = "Requires volunteering.communications.send. Source-scoped paginated delivery status without recipient or message bodies.")
    DeliveryReader.Page deliveries(@RequestParam(required = false) String status,
        @RequestParam(defaultValue = "25") int limit, @RequestParam(defaultValue = "0") int offset);
}
