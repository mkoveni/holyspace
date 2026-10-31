package org.onechurch.childrenministry.incidents.presentation.api;
import org.onechurch.childrenministry.incidents.application.command.accessincident.AccessIncidentHandler;
import org.onechurch.childrenministry.incidents.application.query.listincidentattendance.ListIncidentAttendanceHandler;
import org.onechurch.childrenministry.incidents.application.query.listincidentguardians.ListIncidentGuardiansHandler;
import org.onechurch.childrenministry.incidents.application.query.listincidents.ListIncidentsHandler;
import org.onechurch.identityaccess.application.contract.ResponsibleAccountDirectory;
import org.springframework.http.HttpStatus;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseStatus;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

import java.time.Instant;
import java.time.LocalDate;
import java.util.List;
import java.util.UUID;
@RequestMapping("/api/children/incidents")
@Tag(name = "Children incidents")
public interface IncidentApi {
    record Created(UUID id) {}
    record Report(@NotNull UUID requestId, @NotNull UUID childId, @NotNull UUID sessionId, @NotNull UUID segmentId,
        @NotBlank String category, @NotBlank String severity, @NotNull Instant occurredAt,
        @NotBlank @Size(max = 5000) String description, @NotBlank @Size(max = 3000) String immediateAction,
        @NotNull LocalDate reviewOn, @NotNull LocalDate retainUntil) {
        @Override
        public String toString() {
            return "IncidentReport[restricted]";
        }
    }
    record Access(@Min(1) int limit, @Min(0) int offset) {}
    record FollowUp(@Min(0) long expectedVersion, @NotBlank String action, UUID ownerId, LocalDate reviewOn,
        UUID guardianAuthorityId, @NotBlank @Size(max = 3000) String note) {
        @Override
        public String toString() {
            return "IncidentFollowUp[restricted]";
        }
    }
    record Purge(@Min(1) int limit) {}
    record Purged(int purged) {}
    @PostMapping
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "Record an incident", description = "Requires children.incidents.record and present assigned-session scope, or children.incidents.manage. Attendance segment and event time must match. Retry keys are actor-scoped and reject changed payloads. Private contents never enter the ordinary audit log.")
    Created report(@Valid @RequestBody Report body, @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @GetMapping
    @Operation(summary = "List restricted incident metadata", description = "Requires children.incidents.manage. No descriptions, actions or private notes. Read-only paging; expired retention records are excluded. Overdue/unassigned totals precede the overdue-only filter.")
    ListIncidentsHandler.View list(@RequestParam(required = false) UUID sessionId,
        @RequestParam(required = false) UUID childId, @RequestParam(defaultValue = "ACTIVE") String status,
        @RequestParam(defaultValue = "false") boolean overdueOnly, @RequestParam(defaultValue = "25") int limit,
        @RequestParam(defaultValue = "0") int offset);
    @PostMapping("/{id}/access")
    @Operation(summary = "Open private incident details", description = "Requires children.incidents.manage. Audits every successful access, including each paged history read. Retention is enforced. This response contains restricted incident information.")
    AccessIncidentHandler.View access(@PathVariable UUID id, @Valid @RequestBody Access body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @PostMapping("/{id}/follow-up")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Record incident follow-up", description = "Requires children.incidents.manage. Versioned actions: TRIAGED, ACTION, GUARDIAN_CONTACT, CLOSED, REOPENED. Triage needs an active authorized owner and review date; contact needs current verified adult guardian authority. Closure requires ownership and a resolution note. Notes are retained privately; general audit entries carry no payload.")
    void followUp(@PathVariable UUID id, @Valid @RequestBody FollowUp body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @GetMapping("/attendance")
    @Operation(summary = "Find attendance for an incident report", description = "Requires assigned-session incident reporting scope or incident-management access. Paged child identity and attendance intervals only; search precedes pagination.")
    ListIncidentAttendanceHandler.View attendance(@RequestParam UUID sessionId,
        @RequestParam(defaultValue = "") String search, @RequestParam(defaultValue = "25") int limit,
        @RequestParam(defaultValue = "0") int offset, @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @GetMapping("/{id}/guardians")
    @Operation(summary = "Find current guardians for incident contact", description = "Requires children.incidents.manage. Returns authority identifiers and eligible guardian names only, without private evidence.")
    List<ListIncidentGuardiansHandler.Guardian> guardians(@PathVariable UUID id);
    @GetMapping("/owners")
    @Operation(summary = "Find authorized incident owners", description = "Requires children.incidents.manage. Name search examines up to 100 accounts, returns at most 25 active incident coordinators. Narrow the name for larger directories.")
    List<ResponsibleAccountDirectory.Entry> owners(@RequestParam(defaultValue = "") String search);
    @PostMapping("/purge-expired")
    @Operation(summary = "Remove expired private incident details", description = "Requires children.incidents.manage. Bounded 1–100; removes descriptions and private note/guardian payloads while preserving incident/event metadata and a purge audit. GET requests never purge.")
    Purged purge(@Valid @RequestBody Purge body, @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
}
