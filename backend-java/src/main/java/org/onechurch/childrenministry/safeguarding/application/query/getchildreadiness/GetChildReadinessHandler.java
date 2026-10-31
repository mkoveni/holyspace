package org.onechurch.childrenministry.safeguarding.application.query.getchildreadiness;
import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.participation.domain.Programme;
import org.onechurch.childrenministry.safeguarding.application.ChildReadiness;
import org.onechurch.childrenministry.safeguarding.application.ReadinessView;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonDirectory;

import java.util.List;
public final class GetChildReadinessHandler {
    private final PermissionChecker authorization;
    private final ParticipationReader participation;
    private final PersonDirectory people;
    private final ChildReadiness readiness;
    public GetChildReadinessHandler(PermissionChecker authorization, ParticipationReader participation,
        PersonDirectory people, ChildReadiness readiness) {
        this.authorization = authorization;
        this.participation = participation;
        this.people = people;
        this.readiness = readiness;
    }
    public ReadinessView handle(GetChildReadinessQuery query) {
        authorization.requirePermission("children.read");
        Programme programme =
            participation.programme(query.programmeId()).orElseThrow(() -> ChildrenException.missing("Programme"));
        people.get(query.childId()).orElseThrow(() -> ChildrenException.missing("Person"));
        return readiness.assess(programme, List.of(query.childId())).get(query.childId());
    }
}
