package org.onechurch.volunteering.presentation;

import org.onechurch.volunteering.application.RosterPlanning;
import org.onechurch.volunteering.application.query.previewvolunteerschedule.PreviewVolunteerScheduleHandler;
import org.onechurch.volunteering.application.query.previewvolunteerschedule.PreviewVolunteerScheduleQuery;
import org.onechurch.volunteering.presentation.api.VolunteerSchedulePreviewApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.web.bind.annotation.RestController;

import java.util.List;

@RestController
public class VolunteerSchedulePreviewController implements VolunteerSchedulePreviewApi {
    private final PreviewVolunteerScheduleHandler handler;
    public VolunteerSchedulePreviewController(PreviewVolunteerScheduleHandler handler) {
        this.handler = handler;
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.read')")
    public List<RosterPlanning.Draft> preview(PreviewVolunteerScheduleQuery body) {
        return handler.handle(body);
    }
}
