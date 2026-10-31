package org.onechurch.childrenministry.safeguarding.presentation.api;
import org.onechurch.childrenministry.safeguarding.domain.SupportRecord;
import org.springframework.http.HttpStatus;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.PutMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.ResponseStatus;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.media.Schema;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Max;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

import java.time.LocalDate;
import java.util.UUID;
@Tag(name = "Children Support")
@RequestMapping("/api/children")
public interface SupportApi {
    @Schema(name = "ChildrenSaveChildSupportRequest")
    record SaveChildSupportRequest(@NotNull UUID childId, @Min(0) long expectedVersion,
        @Size(max = 2000) String allergies, @Size(max = 2000) String careInstructions,
        @Size(max = 2000) String restrictedNotes, @NotNull LocalDate reviewOn, @NotNull LocalDate retainUntil) {
        @Override
        public String toString() {
            return "SaveChildSupportRequest[redacted]";
        }
    }
    @PutMapping("/support")
    @Operation(summary = "Save Child Support", description = "Requires children.manage and children.support. Version conflicts require reloading the current resource.")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    void saveChildSupport(@Valid @RequestBody SaveChildSupportRequest body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @Schema(name = "ChildrenAccessChildSupportRequest")
    record AccessChildSupportRequest(@NotNull UUID childId, UUID sessionId) {}
    @PostMapping("/support/access")
    @Operation(summary = "Access Child Support", description = "Requires children.support and session scope or children.manage for access without a session.")
    SupportRecord accessChildSupport(@Valid @RequestBody AccessChildSupportRequest body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @Schema(name = "ChildrenPurgeExpiredSupportRequest")
    record PurgeExpiredSupportRequest(@Min(1) @Max(100) int limit) {
        @Override
        public String toString() {
            return "PurgeExpiredSupportRequest[redacted]";
        }
    }
    @PostMapping("/support/purge")
    @Operation(summary = "Purge Expired Support", description = "Requires children.support and children.supervise. Deletes only expired payloads under lock.")
    int purgeExpiredSupport(@Valid @RequestBody PurgeExpiredSupportRequest body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
}
