package org.onechurch.childrenministry.participation.application.query.listclassassignments;

import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.participation.domain.ClassAssignment;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.List;

public final class ListClassAssignmentsHandler {
    private final PermissionChecker authorization;
    private final ParticipationReader participation;
    public ListClassAssignmentsHandler(PermissionChecker authorization, ParticipationReader participation) {
        this.authorization = authorization;
        this.participation = participation;
    }
    public List<ClassAssignment> handle(ListClassAssignmentsQuery command) {
        authorization.requirePermission("children.read");
        ChildrenRules.page(command.limit(), command.offset());
        return participation.assignments(command.childId(), command.limit(), command.offset());
    }
}
