package org.onechurch.childrenministry.sessions.presentation.api;
import org.onechurch.childrenministry.sessions.application.SessionView;
import org.onechurch.childrenministry.sessions.application.StationAttendance;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;

import java.time.Instant;
import java.util.List;
import java.util.UUID;
@Tag(name = "Children Station")
@RequestMapping("/api/children/station/sessions")
public interface StationApi {
    @GetMapping
    @Operation(summary = "Find my station sessions", description = "Requires children.station. Returns present assignments only; supervisors can discover all sessions. Paging is applied after assignment scope in SQL.")
    List<SessionView> list(@RequestParam(required = false) Instant from, @RequestParam(required = false) Instant until,
        @RequestParam(defaultValue = "25") int limit, @RequestParam(defaultValue = "0") int offset,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @GetMapping("/{sessionId}")
    @Operation(summary = "Open my station session", description = "Requires children.station and present assignment, or supervisor scope.")
    SessionView get(@PathVariable UUID sessionId, @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);

    @GetMapping("/{sessionId}/attendance")
    @Operation(summary = "Read my station attendance", description = "Requires assigned station access. Names and attendance only; no People directory permission or care payload is needed.")
    List<StationAttendance> roster(@PathVariable UUID sessionId, @RequestParam(defaultValue = "100") int limit,
        @RequestParam(defaultValue = "0") int offset, @RequestParam(defaultValue = "true") boolean openOnly,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
}
