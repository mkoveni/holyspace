package org.onechurch.childrenministry.participation.application.query.getprogramme;

import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.participation.domain.Programme;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

public final class GetProgrammeHandler {
    private final PermissionChecker authorization;
    private final ParticipationReader participation;
    public GetProgrammeHandler(PermissionChecker authorization, ParticipationReader participation) {
        this.authorization = authorization;
        this.participation = participation;
    }
    public Programme handle(GetProgrammeQuery command) {
        authorization.requirePermission("children.read");
        return participation.programme(command.programmeId()).orElseThrow(() -> ChildrenException.missing("Programme"));
    }
}
