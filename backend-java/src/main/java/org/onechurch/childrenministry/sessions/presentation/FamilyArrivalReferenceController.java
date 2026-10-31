package org.onechurch.childrenministry.sessions.presentation;
import org.onechurch.childrenministry.sessions.application.query.getfamilyarrivalreference.GetFamilyArrivalReferenceHandler;
import org.onechurch.childrenministry.sessions.application.query.getfamilyarrivalreference.GetFamilyArrivalReferenceQuery;
import org.onechurch.childrenministry.sessions.presentation.api.FamilyArrivalReferenceApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.web.bind.annotation.ModelAttribute;
import org.springframework.web.bind.annotation.RestController;

import jakarta.servlet.http.HttpServletResponse;

import java.util.UUID;
@RestController
@PreAuthorize("hasAuthority('children.station')")
public class FamilyArrivalReferenceController implements FamilyArrivalReferenceApi {
    private final GetFamilyArrivalReferenceHandler handler;
    public FamilyArrivalReferenceController(GetFamilyArrivalReferenceHandler handler) {
        this.handler = handler;
    }
    @ModelAttribute
    void privateResponses(HttpServletResponse response) {
        response.setHeader("Cache-Control", "no-store");
    }
    @Override
    public GetFamilyArrivalReferenceHandler.FamilyArrivalReview review(UUID sessionId, UUID childId) {
        return handler.handle(new GetFamilyArrivalReferenceQuery(sessionId, childId));
    }
}
