package org.onechurch.communications.presentation.api;
import org.onechurch.communications.application.contract.InboxReader;
import org.springframework.http.HttpStatus;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PutMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseStatus;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotNull;

import java.util.List;
import java.util.UUID;
@Tag(name = "Member inbox")
public interface MemberInboxApi {
    record NotificationReadRequest(@NotNull Boolean read) {}
    record NotificationPreferenceRequest(@NotNull Boolean enabled, @Min(0) long expectedVersion) {}
    @GetMapping("/api/member/notifications")
    @Operation(summary = "Read your own inbox", description = "Live authenticated account only. Read-only, newest first, no membership or staff access inferred. Generic notices contain no child, care or collection data.")
    InboxReader.MemberInbox list(@RequestParam(defaultValue = "false") boolean unreadOnly,
        @RequestParam(defaultValue = "20") int limit, @RequestParam(defaultValue = "0") int offset);
    @PutMapping("/api/member/notifications/{id}/read")
    @Operation(summary = "Mark your own notification read or unread", description = "Idempotent desired state. Another account’s identifier returns 404.")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    void read(@PathVariable UUID id, @Valid @RequestBody NotificationReadRequest body);
    @GetMapping("/api/member/notification-preferences")
    @Operation(summary = "Read your inbox topic preferences", description = "Defaults enabled. Does not change email consent or delivery preferences. No push channel is activated.")
    List<InboxReader.MemberNotificationPreference> preferences();
    @PutMapping("/api/member/notification-preferences/{topic}")
    @Operation(summary = "Change one inbox topic preference", description = "Optimistic version required; controls future notices only. Existing inbox items and source duties/bookings remain accessible.")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    void preference(@PathVariable String topic, @Valid @RequestBody NotificationPreferenceRequest body);
}
