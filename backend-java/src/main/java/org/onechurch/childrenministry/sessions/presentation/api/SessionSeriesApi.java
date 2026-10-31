package org.onechurch.childrenministry.sessions.presentation.api;
import org.onechurch.childrenministry.sessions.application.SessionSeriesDraft;
import org.onechurch.childrenministry.sessions.application.SessionSeriesPage;
import org.onechurch.childrenministry.sessions.application.SessionSeriesPreview;
import org.onechurch.childrenministry.sessions.domain.WeeklyRecurrence;
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
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

import java.util.UUID;
@Tag(name = "Children Recurring Sessions")
public interface SessionSeriesApi {
    record Draft(@NotBlank @Size(max = 120) String name, @NotNull UUID sourceSessionId, @Min(0) long sourceVersion,
        @NotNull WeeklyRecurrence recurrence) {
        public SessionSeriesDraft toDraft() {
            return new SessionSeriesDraft(name, sourceSessionId, sourceVersion, recurrence);
        }
    }
    record Create(@NotNull UUID requestId, @Valid @NotNull Draft draft) {}
    record Created(UUID id) {}
    record Cancel(@Min(0) long expectedVersion, @NotBlank @Size(max = 500) String reason) {}
    @PostMapping("/api/children/session-series/preview")
    @Operation(summary = "Preview a weekly session series", description = "Requires children.manage. Read-only preview using the source class, room, seats and team. Location-local wall times remain stable through daylight saving; ambiguous/nonexistent times are rejected. Choose 1–52 dates every 1–4 weeks within 366 days and explicit excluded dates. Clashes block creation; staffing warnings allow planning. No workers are marked present.")
    SessionSeriesPreview preview(@Valid @RequestBody Draft body);
    @PostMapping("/api/children/session-series")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "Create a complete planned series", description = "Requires children.manage. Rechecks source version, future dates and conflicts under lock. Creates every included session atomically; a concurrent room/class conflict rolls back the entire series. Actor-scoped request IDs make retries idempotent and reject changed payloads. Copied team members are absent; clearance, capacity and presence are checked when opening each session.")
    Created create(@Valid @RequestBody Create body, @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @GetMapping("/api/children/session-series")
    @Operation(summary = "List recurring session series", description = "Requires children.read. Paginated series with planned and cancelled session counts.")
    SessionSeriesPage list(@RequestParam(defaultValue = "25") int limit, @RequestParam(defaultValue = "0") int offset);
    @GetMapping("/api/children/session-series/{id}")
    @Operation(summary = "Read the sessions in a series", description = "Requires children.read. Returns the bounded series of at most 52 sessions, preserving individual status and history.")
    SessionSeriesPage.Detail get(@PathVariable UUID id);
    @PostMapping("/api/children/session-series/{id}/cancel")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Cancel remaining future planned dates", description = "Requires children.manage, current series version and an audit reason. Locks sessions in UUID order. Only future PLANNED sessions are cancelled; past, open, review, closed and already-cancelled sessions retain their status and history.")
    void cancel(@PathVariable UUID id, @Valid @RequestBody Cancel body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
}
