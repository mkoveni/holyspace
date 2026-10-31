package org.onechurch.childrenministry.sessions.application.query.getstationroster;
import org.onechurch.childrenministry.sessions.application.AttendanceView;
import org.onechurch.childrenministry.sessions.application.StaffScope;
import org.onechurch.childrenministry.sessions.application.StationAttendance;
import org.onechurch.childrenministry.sessions.application.contract.AttendanceReader;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonDirectory;

import java.util.List;
public final class GetStationRosterHandler {
    private final PermissionChecker authorization;
    private final StaffScope scope;
    private final AttendanceReader attendance;
    private final PersonDirectory people;
    public GetStationRosterHandler(PermissionChecker authorization, StaffScope scope, AttendanceReader attendance,
        PersonDirectory people) {
        this.authorization = authorization;
        this.scope = scope;
        this.attendance = attendance;
        this.people = people;
    }
    public List<StationAttendance> handle(GetStationRosterQuery query) {
        authorization.requirePermission(query.actorId(), "children.station");
        scope.require(query.actorId(), query.sessionId());
        ChildrenRules.page(query.limit(), query.offset());
        return attendance.roster(query.sessionId(), query.openOnly(), query.limit(), query.offset())
            .stream()
            .map(value -> new StationAttendance(AttendanceView.from(value),
                people.get(value.childId()).map(PersonDirectory.Entry::name).orElse("Child profile unavailable")))
            .toList();
    }
}
