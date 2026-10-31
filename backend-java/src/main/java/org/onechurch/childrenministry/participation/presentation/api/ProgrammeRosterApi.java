package org.onechurch.childrenministry.participation.presentation.api;
import org.onechurch.childrenministry.participation.application.query.listprogrammechildren.ProgrammeChildrenPage;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.RequestParam;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;

import java.util.UUID;
@Tag(name = "children-participation")
public interface ProgrammeRosterApi {
    @GetMapping("/api/children/programmes/{programmeId}/children")
    @Operation(summary = "List enrolled children", description = "Requires children.read. Searches all programme enrolments by child name; current class placements and date-based statuses use the church time zone. No safeguarding information is returned.")
    ProgrammeChildrenPage list(@PathVariable("programmeId") UUID programmeId,
        @RequestParam(name = "search", defaultValue = "") String search,
        @RequestParam(name = "status", defaultValue = "ALL") String status,
        @RequestParam(name = "limit", defaultValue = "25") int limit,
        @RequestParam(name = "offset", defaultValue = "0") int offset);
}
