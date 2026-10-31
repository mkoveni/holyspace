package org.onechurch.communications.presentation.api;
import org.onechurch.communications.application.contract.InboxReader;
import org.onechurch.communications.application.contract.PushReader;
import org.springframework.http.HttpStatus;
import org.springframework.web.bind.annotation.DeleteMapping;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PutMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.ResponseStatus;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

import java.util.UUID;
@Tag(name = "Member device push")
public interface MemberPushApi {
    record PushDeviceRegistrationRequest(@NotBlank @Size(max = 100) String installationSecret,
        @NotBlank @Size(max = 240) String token, @NotBlank @Size(max = 10) String platform, @NotNull UUID projectId) {
        @Override
        public String toString() {
            return "PushDeviceRegistrationRequest[redacted]";
        }
    }
    record PushTopicPreferenceRequest(@NotNull Boolean enabled, @Min(0) long expectedVersion) {}
    @GetMapping("/api/member/push")
    @Operation(summary = "Read your push setup and device registrations", description = "Read-only live authenticated account. Topics default off; no tokens, installation secrets or session identifiers are exposed. Registration is bound to its issuing session and lease.")
    PushReader.MemberPushSettings settings();
    @PutMapping("/api/member/push/devices/{id}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Enable or renew this installation for push", description = "Requires explicit device permission, configured project and a private installation secret. Derives account/session from trusted caller. Token encrypted at rest. Rebinding to another account requires previous session revocation/expiry. No historic notice backfill.")
    void register(@PathVariable UUID id, @Valid @RequestBody PushDeviceRegistrationRequest body);
    @DeleteMapping("/api/member/push/devices/{id}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Disable one of your push devices", description = "Own-account scope, including remote self-revocation. Invalidates pending jobs. Device permission is separate and can be changed in OS settings.")
    void revoke(@PathVariable UUID id);
    @PutMapping("/api/member/push/preferences/{topic}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Change your push topic preference", description = "Versioned opt-in independent of inbox/email. Changing a preference invalidates queued jobs for its previous revision.")
    void preference(@PathVariable String topic, @Valid @RequestBody PushTopicPreferenceRequest body);
    @GetMapping("/api/member/notifications/{id}")
    @Operation(summary = "Resolve your notification before opening a push", description = "Own account only, 404 for foreign IDs. Does not mark read or grant destination authority. Only fixed member destinations are returned, never URLs.")
    InboxReader.MemberNotification notification(@PathVariable UUID id);
}
