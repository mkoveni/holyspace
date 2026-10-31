package org.onechurch.childrenministry.participation.application.query.listclasses;

import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.participation.domain.MinistryClass;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.List;

public final class ListClassesHandler {
    private final PermissionChecker authorization;
    private final ParticipationReader participation;
    public ListClassesHandler(PermissionChecker authorization, ParticipationReader participation) {
        this.authorization = authorization;
        this.participation = participation;
    }
    public List<MinistryClass> handle(ListClassesQuery command) {
        authorization.requirePermission("children.read");
        return participation.classes(command.programmeId());
    }
}
