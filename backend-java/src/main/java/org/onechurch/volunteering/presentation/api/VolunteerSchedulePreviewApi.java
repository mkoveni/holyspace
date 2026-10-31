package org.onechurch.volunteering.presentation.api;

import org.onechurch.volunteering.application.RosterPlanning;
import org.onechurch.volunteering.application.query.previewvolunteerschedule.PreviewVolunteerScheduleQuery;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;

import java.util.List;

@Tag(name = "Volunteers and serving teams")
public interface VolunteerSchedulePreviewApi {
    @PostMapping("/api/volunteering/shifts/preview")
    @Operation(summary = "Preview recurring standalone shifts", description = "Read-only preview of 1–25 dates in an explicit time zone. Rejects ambiguous daylight-saving times. Apply the returned drafts through the atomic bulk command; preview never reserves staff.")
    List<RosterPlanning.Draft> preview(@Valid @RequestBody PreviewVolunteerScheduleQuery body);
}
