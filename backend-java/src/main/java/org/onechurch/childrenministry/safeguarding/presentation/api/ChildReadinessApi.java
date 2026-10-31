package org.onechurch.childrenministry.safeguarding.presentation.api;
import org.onechurch.childrenministry.safeguarding.application.ReadinessView;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;

import java.util.UUID;
@Tag(name = "children-participation")
public interface ChildReadinessApi {
    @GetMapping("/api/children/programmes/{programmeId}/children/{childId}/readiness")
    @Operation(summary = "Check child preparation", description = "Requires children.read. Checks current programme preparation using church-local today and current permissions. This is not a guarantee of session admission or capacity. Returns completion flags, never care or guardian evidence.")
    ReadinessView get(@PathVariable("programmeId") UUID programmeId, @PathVariable("childId") UUID childId);
}
