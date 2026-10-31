package org.onechurch.childrenministry.sessions.presentation;

import org.onechurch.childrenministry.sessions.application.command.importservingstaff.ImportServingStaffCommand;
import org.onechurch.childrenministry.sessions.application.command.importservingstaff.ImportServingStaffHandler;
import org.onechurch.childrenministry.sessions.application.query.listservingcandidates.ListServingCandidatesHandler;
import org.onechurch.childrenministry.sessions.application.query.listservingcandidates.ListServingCandidatesQuery;
import org.onechurch.childrenministry.sessions.presentation.api.ServingStaffApi;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.List;
import java.util.UUID;

@RestController
public class ServingStaffController implements ServingStaffApi {
    private final PermissionChecker auth;
    private final ListServingCandidatesHandler list;
    private final ImportServingStaffHandler add;
    public ServingStaffController(PermissionChecker auth, ListServingCandidatesHandler list,
        ImportServingStaffHandler add) {
        this.auth = auth;
        this.list = list;
        this.add = add;
    }
    public List<ListServingCandidatesHandler.Candidate> candidates(UUID id, OAuth2AuthenticatedPrincipal p) {
        auth.requirePermission("children.manage");
        return list.handle(new ListServingCandidatesQuery(UUID.fromString(p.getName()), id));
    }
    public void add(UUID id, Import body, OAuth2AuthenticatedPrincipal p) {
        auth.requirePermission("children.manage");
        add.handle(new ImportServingStaffCommand(UUID.fromString(p.getName()), id, body.expectedVersion(),
            body.assignmentId(), body.responsibility(), body.present()));
    }
}
