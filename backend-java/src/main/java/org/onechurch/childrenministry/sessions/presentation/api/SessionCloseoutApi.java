package org.onechurch.childrenministry.sessions.presentation.api;
import org.onechurch.childrenministry.sessions.application.query.getsessioncloseout.SessionCloseout;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;

import java.util.UUID;
@Tag(name = "Children Session")
public interface SessionCloseoutApi {
    @GetMapping("/api/children/sessions/{sessionId}/closeout")
    @Operation(summary = "Review session closeout", description = "Requires children.manage. Counts all session visit segments, including transfers and repeat visits, independently of roster pagination. Label and failed-handover warnings apply only to children still present. No codes, private care, collector identity or exception reasons are returned. Closure rechecks attendance under lock.")
    SessionCloseout get(@PathVariable UUID sessionId, @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
}
