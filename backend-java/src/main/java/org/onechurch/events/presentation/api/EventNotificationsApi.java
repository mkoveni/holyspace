package org.onechurch.events.presentation.api;
import org.onechurch.communications.application.contract.DeliveryReader;
import org.onechurch.events.application.EventMessageReview;
import org.onechurch.people.application.contract.PersonEmailDirectory;
import org.springframework.http.HttpStatus;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseStatus;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.media.Schema;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

import java.util.List;
import java.util.UUID;
@Tag(name = "EventNotifications")
public interface EventNotificationsApi {
    @Schema(name = "EventDayCreated")
    record Created(UUID id) {}
    @Schema(name = "ApproveEventNotificationBody")
    record ApproveEventNotificationBody(@NotNull UUID requestId, @NotNull UUID contactId, @NotBlank String purpose,
        @NotBlank @Size(max = 2000) String message, @NotBlank String reviewHash,
        @NotBlank @Size(max = 500) String verificationEvidence) {
        @Override
        public String toString() {
            return "ApproveEventNotificationBody[redacted]";
        }
    }
    @PostMapping("/api/events/registrations/{id}/notifications")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "Approve Event Notification", description = "Requires events.communications.send. Attendance is independent of bookings. Notice approval freezes reviewed content and dispatch rechecks eligibility.")
    Created approveEventNotification(@PathVariable UUID id, @Valid @RequestBody ApproveEventNotificationBody b,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @GetMapping("/api/events/registrations/{id}/message-contacts")

    @Operation(summary = "List Event Message Contacts", description = "Requires events.communications.send. Attendance is independent of bookings. Notice approval freezes reviewed content and dispatch rechecks eligibility.")
    List<PersonEmailDirectory.Email> listEventMessageContacts(@PathVariable UUID id);
    @Schema(name = "ReviewEventNotificationBody")
    record ReviewEventNotificationBody(@NotNull UUID contactId, @NotBlank String purpose,
        @NotBlank @Size(max = 2000) String message) {
        @Override
        public String toString() {
            return "ReviewEventNotificationBody[redacted]";
        }
    }
    @PostMapping("/api/events/registrations/{id}/notification-review")

    @Operation(summary = "Review Event Notification", description = "Requires events.communications.send. Attendance is independent of bookings. Notice approval freezes reviewed content and dispatch rechecks eligibility.")
    EventMessageReview.Review reviewEventNotification(@PathVariable UUID id,
        @Valid @RequestBody ReviewEventNotificationBody b);
    @GetMapping("/api/events/deliveries")

    @Operation(summary = "List Event Deliveries", description = "Requires events.communications.send. Attendance is independent of bookings. Notice approval freezes reviewed content and dispatch rechecks eligibility.")
    DeliveryReader.Page listEventDeliveries(@RequestParam(required = false) String status,
        @RequestParam(defaultValue = "25") int limit, @RequestParam(defaultValue = "0") int offset);
}
