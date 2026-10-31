package org.onechurch.childrenministry.sessions.presentation.api;
import org.onechurch.childrenministry.participation.domain.Location;
import org.onechurch.childrenministry.participation.domain.Room;
import org.onechurch.childrenministry.sessions.application.SessionView;
import org.onechurch.childrenministry.sessions.domain.SessionStatus;
import org.onechurch.childrenministry.sessions.domain.StaffRole;
import org.onechurch.childrenministry.sessions.domain.Worker;
import org.springframework.http.HttpStatus;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.PutMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseStatus;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.media.Schema;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Max;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

import java.time.Instant;
import java.util.List;
import java.util.UUID;
@Tag(name = "Children Session")
@RequestMapping("/api/children")
public interface SessionApi {
    @Schema(name = "ChildrenSessionCreated")
    record Created(UUID id) {}
    @Schema(name = "ChildrenCreateLocationRequest")
    record CreateLocationRequest(@Size(max = 2000) String name, @NotBlank @Size(max = 2000) String timeZone) {}
    @PostMapping("/locations")
    @Operation(summary = "Create Location", description = "Requires children.manage. Version conflicts require reloading the current resource.")
    @ResponseStatus(HttpStatus.CREATED)
    Created createLocation(@Valid @RequestBody CreateLocationRequest body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @Schema(name = "ChildrenCreateRoomRequest")
    record CreateRoomRequest(@NotNull UUID locationId, @Size(max = 2000) String name,
        @Min(1) @Max(10000) int capacity) {}
    @PostMapping("/rooms")
    @Operation(summary = "Create Room", description = "Requires children.manage. Version conflicts require reloading the current resource.")
    @ResponseStatus(HttpStatus.CREATED)
    Created createRoom(@Valid @RequestBody CreateRoomRequest body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @Schema(name = "ChildrenRegisterWorkerRequest")
    record RegisterWorkerRequest(@NotNull UUID personId, @NotNull UUID accountId, @NotNull Instant screenedUntil,
        @NotNull Instant trainedUntil, @Size(max = 2000) String evidenceReference) {}
    @PostMapping("/workers")
    @Operation(summary = "Register Worker", description = "Requires children.safeguarding. Version conflicts require reloading the current resource.")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    void registerWorker(@Valid @RequestBody RegisterWorkerRequest body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @Schema(name = "ChildrenReviewWorkerRequest")
    record ReviewWorkerRequest(@NotNull UUID personId, @Min(0) long expectedVersion, @NotNull Instant screenedUntil,
        @NotNull Instant trainedUntil, @Size(max = 2000) String evidenceReference, boolean revoked) {}
    @PutMapping("/workers")
    @Operation(summary = "Review Worker", description = "Requires children.safeguarding. Version conflicts require reloading the current resource.")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    void reviewWorker(@Valid @RequestBody ReviewWorkerRequest body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @Schema(name = "ChildrenCreateSessionRequest")
    record CreateSessionRequest(@NotNull UUID classId, @NotNull UUID roomId, @NotNull Instant startsAt,
        @NotNull Instant endsAt, @Min(1) @Max(10000) int seatLimit) {}
    @PostMapping("/sessions")
    @Operation(summary = "Create Session", description = "Requires children.manage. Version conflicts require reloading the current resource.")
    @ResponseStatus(HttpStatus.CREATED)
    Created createSession(@Valid @RequestBody CreateSessionRequest body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @Schema(name = "ChildrenSetSessionStaffRequest")
    record SetSessionStaffRequest(@NotNull UUID sessionId, @Min(0) long expectedVersion, @NotNull UUID workerId,
        @NotNull StaffRole role, boolean present) {}
    @PutMapping("/sessions/staff")
    @Operation(summary = "Set Session Staff", description = "Requires children.manage. Version conflicts require reloading the current resource.")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    void setSessionStaff(@Valid @RequestBody SetSessionStaffRequest body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @Schema(name = "ChildrenSetSessionStatusRequest")
    record SetSessionStatusRequest(@NotNull UUID sessionId, @Min(0) long expectedVersion, @NotNull SessionStatus status,
        @Size(max = 2000) String reason, Boolean acknowledgeIncidents) {}
    @PutMapping("/sessions/status")
    @Operation(summary = "Set Session Status", description = "Requires children.manage. Version conflicts require reloading the current resource.")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    void setSessionStatus(@Valid @RequestBody SetSessionStatusRequest body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @GetMapping("/sessions")
    @Operation(summary = "List Sessions", description = "Requires children.read. Version conflicts require reloading the current resource.")
    List<SessionView> listSessions(@RequestParam(name = "locationId", required = false) UUID locationId,
        @RequestParam(name = "from", required = false) Instant from,
        @RequestParam(name = "until", required = false) Instant until,
        @RequestParam(name = "limit", defaultValue = "50") int limit,
        @RequestParam(name = "offset", defaultValue = "0") int offset);
    @GetMapping("/sessions/{sessionId}")
    @Operation(summary = "Get Session", description = "Requires children.read. Version conflicts require reloading the current resource.")
    SessionView getSession(@PathVariable("sessionId") UUID sessionId);
    @GetMapping("/rooms")
    @Operation(summary = "List Rooms", description = "Requires children.read. Version conflicts require reloading the current resource.")
    List<Room> listRooms(@RequestParam(name = "locationId", required = false) UUID locationId);
    @GetMapping("/workers/{personId}")
    @Operation(summary = "Get Worker", description = "Requires children.safeguarding. Version conflicts require reloading the current resource.")
    Worker getWorker(@PathVariable("personId") UUID personId);
    @GetMapping("/locations")
    @Operation(summary = "List Locations", description = "Requires children.read. Version conflicts require reloading the current resource.")
    List<Location> listLocations(@RequestParam(name = "limit", defaultValue = "50") int limit,
        @RequestParam(name = "offset", defaultValue = "0") int offset);
}
