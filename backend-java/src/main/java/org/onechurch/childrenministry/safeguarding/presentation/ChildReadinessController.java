package org.onechurch.childrenministry.safeguarding.presentation;
import org.onechurch.childrenministry.safeguarding.application.ReadinessView;
import org.onechurch.childrenministry.safeguarding.application.query.getchildreadiness.GetChildReadinessHandler;
import org.onechurch.childrenministry.safeguarding.application.query.getchildreadiness.GetChildReadinessQuery;
import org.onechurch.childrenministry.safeguarding.presentation.api.ChildReadinessApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.web.bind.annotation.RestController;

import java.util.UUID;
@RestController
public class ChildReadinessController implements ChildReadinessApi {
    private final GetChildReadinessHandler handler;
    public ChildReadinessController(GetChildReadinessHandler handler) {
        this.handler = handler;
    }
    @Override
    @PreAuthorize("hasAuthority('children.read')")
    public ReadinessView get(UUID programmeId, UUID childId) {
        return handler.handle(new GetChildReadinessQuery(programmeId, childId));
    }
}
