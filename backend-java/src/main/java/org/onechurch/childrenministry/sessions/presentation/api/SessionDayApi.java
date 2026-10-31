package org.onechurch.childrenministry.sessions.presentation.api;
import org.onechurch.childrenministry.sessions.application.query.getsessionday.SessionDayPage;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.RequestParam;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;

import java.util.UUID;
@Tag(name = "Children Station")
public interface SessionDayApi {
    @GetMapping("/api/children/station/sessions/{sessionId}/day")
    @Operation(summary = "Run a session day", description = "Requires children.station and present assignment or supervisor scope. Searches the entire selected session view before pagination. Expected children receive preparation checks for the session date and guardian consent through its end. Returns no care or guardian evidence. PLANNED preflight checks consent from session start; open-session checks use the current time. Check-in revalidates under lock.")
    SessionDayPage get(@PathVariable UUID sessionId, @RequestParam(defaultValue = "") String search,
        @RequestParam(defaultValue = "EXPECTED") String view, @RequestParam(defaultValue = "100") int limit,
        @RequestParam(defaultValue = "0") int offset, @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
}
