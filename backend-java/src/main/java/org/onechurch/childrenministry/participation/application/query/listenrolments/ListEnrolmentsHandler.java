package org.onechurch.childrenministry.participation.application.query.listenrolments;

import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.participation.domain.Enrolment;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.List;

public final class ListEnrolmentsHandler {
    private final PermissionChecker authorization;
    private final ParticipationReader participation;
    public ListEnrolmentsHandler(PermissionChecker authorization, ParticipationReader participation) {
        this.authorization = authorization;
        this.participation = participation;
    }
    public List<Enrolment> handle(ListEnrolmentsQuery command) {
        authorization.requirePermission("children.read");
        ChildrenRules.page(command.limit(), command.offset());
        return participation.enrolments(command.childId(), command.limit(), command.offset());
    }
}
