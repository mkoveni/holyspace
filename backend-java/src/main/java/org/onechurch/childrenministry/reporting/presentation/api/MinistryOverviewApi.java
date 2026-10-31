package org.onechurch.childrenministry.reporting.presentation.api;
import org.onechurch.childrenministry.reporting.application.query.getministryoverview.GetMinistryOverviewHandler;
import org.springframework.web.bind.annotation.GetMapping;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;
@Tag(name = "Children ministry overview")
public interface MinistryOverviewApi {
    @GetMapping("/api/children/overview")
    @Operation(summary = "Review the ministry week", description = "Requires children.read, children.safeguarding or children.incidents.manage. Upcoming sessions cover seven church-local dates. Staffing requires children.manage; safeguarding/communications require safeguarding; incident metadata requires incident management. Care metadata is additionally support-gated. Restricted sections are null, not zero. Global totals and top five follow-ups only, with links to paged workspaces. Reads no private incident/care payloads and performs no writes.")
    GetMinistryOverviewHandler.View get();
}
