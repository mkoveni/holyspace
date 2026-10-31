package org.onechurch.childrenministry.participation.application.query.listprogrammes;

import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.participation.domain.Programme;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.util.List;

public final class ListProgrammesHandler {
    private final PermissionChecker authorization;
    private final ParticipationReader participation;
    public ListProgrammesHandler(PermissionChecker authorization, ParticipationReader participation) {
        this.authorization = authorization;
        this.participation = participation;
    }
    public List<Programme> handle(ListProgrammesQuery command) {
        authorization.requirePermission("children.read");
        ChildrenRules.page(command.limit(), command.offset());
        return participation.programmes(ChildrenRules.optional(command.search(), 120), command.limit(),
            command.offset());
    }
}
