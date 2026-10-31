package org.onechurch.childrenministry.sessions.presentation.api;
import org.onechurch.childrenministry.sessions.application.query.getfamilyarrivalreference.GetFamilyArrivalReferenceHandler;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;

import java.util.UUID;
@Tag(name = "Children Attendance")
public interface FamilyArrivalReferenceApi {
    @GetMapping("/api/children/sessions/{sessionId}/arrival-reference/{childId}")
    @Operation(summary = "Review a family check-in reference", description = "Assigned station staff with children.station only. A QR carries child/session identifiers, not authority. Current dated placement and enrolment scope the read. GET never records attendance; use the existing check-in command after reviewing preparation and capacity.")
    GetFamilyArrivalReferenceHandler.FamilyArrivalReview review(@PathVariable UUID sessionId,
        @PathVariable UUID childId);
}
