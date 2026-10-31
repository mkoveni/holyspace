package org.onechurch.childrenministry.participation.presentation;
import org.onechurch.childrenministry.participation.application.query.listprogrammechildren.ListProgrammeChildrenHandler;
import org.onechurch.childrenministry.participation.application.query.listprogrammechildren.ListProgrammeChildrenQuery;
import org.onechurch.childrenministry.participation.application.query.listprogrammechildren.ProgrammeChildrenPage;
import org.onechurch.childrenministry.participation.presentation.api.ProgrammeRosterApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.web.bind.annotation.RestController;

import java.util.UUID;
@RestController
public class ProgrammeRosterController implements ProgrammeRosterApi {
    private final ListProgrammeChildrenHandler handler;
    public ProgrammeRosterController(ListProgrammeChildrenHandler handler) {
        this.handler = handler;
    }
    @Override
    @PreAuthorize("hasAuthority('children.read')")
    public ProgrammeChildrenPage list(UUID programmeId, String search, String status, int limit, int offset) {
        return handler.handle(new ListProgrammeChildrenQuery(programmeId, search, status, limit, offset));
    }
}
