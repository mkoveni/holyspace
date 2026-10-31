package org.onechurch.childrenministry.reporting.presentation;
import org.onechurch.childrenministry.reporting.application.query.getministryoverview.GetMinistryOverviewHandler;
import org.onechurch.childrenministry.reporting.application.query.getministryoverview.GetMinistryOverviewQuery;
import org.onechurch.childrenministry.reporting.presentation.api.MinistryOverviewApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.web.bind.annotation.RestController;
@RestController
public class MinistryOverviewController implements MinistryOverviewApi {
    private final GetMinistryOverviewHandler handler;
    public MinistryOverviewController(GetMinistryOverviewHandler handler) {
        this.handler = handler;
    }
    @Override
    @PreAuthorize("hasAnyAuthority('children.read','children.safeguarding','children.incidents.manage')")
    public GetMinistryOverviewHandler.View get() {
        return handler.handle(new GetMinistryOverviewQuery());
    }
}
