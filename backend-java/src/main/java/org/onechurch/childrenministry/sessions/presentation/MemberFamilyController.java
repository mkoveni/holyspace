package org.onechurch.childrenministry.sessions.presentation;
import org.onechurch.childrenministry.sessions.application.LabelIssue;
import org.onechurch.childrenministry.sessions.application.MemberFamilyViews;
import org.onechurch.childrenministry.sessions.application.command.issuemychildpickuplabel.IssueMyChildPickupLabelCommand;
import org.onechurch.childrenministry.sessions.application.command.issuemychildpickuplabel.IssueMyChildPickupLabelHandler;
import org.onechurch.childrenministry.sessions.application.query.getmychild.GetMyChildHandler;
import org.onechurch.childrenministry.sessions.application.query.getmychild.GetMyChildQuery;
import org.onechurch.childrenministry.sessions.application.query.listmychildattendance.ListMyChildAttendanceHandler;
import org.onechurch.childrenministry.sessions.application.query.listmychildattendance.ListMyChildAttendanceQuery;
import org.onechurch.childrenministry.sessions.application.query.listmychildprogrammes.ListMyChildProgrammesHandler;
import org.onechurch.childrenministry.sessions.application.query.listmychildprogrammes.ListMyChildProgrammesQuery;
import org.onechurch.childrenministry.sessions.application.query.listmychildren.ListMyChildrenHandler;
import org.onechurch.childrenministry.sessions.application.query.listmychildren.ListMyChildrenQuery;
import org.onechurch.childrenministry.sessions.application.query.listmychildsessions.ListMyChildSessionsHandler;
import org.onechurch.childrenministry.sessions.application.query.listmychildsessions.ListMyChildSessionsQuery;
import org.onechurch.childrenministry.sessions.presentation.api.MemberFamilyApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.web.bind.annotation.ModelAttribute;
import org.springframework.web.bind.annotation.RestController;

import jakarta.servlet.http.HttpServletResponse;

import java.util.UUID;
@RestController
@PreAuthorize("isAuthenticated()")
public class MemberFamilyController implements MemberFamilyApi {
    private final ListMyChildrenHandler children;
    private final GetMyChildHandler child;
    private final ListMyChildProgrammesHandler programmes;
    private final ListMyChildSessionsHandler sessions;
    private final ListMyChildAttendanceHandler attendance;
    private final IssueMyChildPickupLabelHandler label;
    public MemberFamilyController(ListMyChildrenHandler children, GetMyChildHandler child,
        ListMyChildProgrammesHandler programmes, ListMyChildSessionsHandler sessions,
        ListMyChildAttendanceHandler attendance, IssueMyChildPickupLabelHandler label) {
        this.children = children;
        this.child = child;
        this.programmes = programmes;
        this.sessions = sessions;
        this.attendance = attendance;
        this.label = label;
    }
    @ModelAttribute
    void privateResponses(HttpServletResponse response) {
        response.setHeader("Cache-Control", "no-store");
    }
    @Override
    public MemberFamilyViews.FamilyPage<MemberFamilyViews.FamilyChild> children(int limit, int offset) {
        return children.handle(new ListMyChildrenQuery(limit, offset));
    }
    @Override
    public MemberFamilyViews.FamilyChild child(UUID childId) {
        return child.handle(new GetMyChildQuery(childId));
    }
    @Override
    public MemberFamilyViews.FamilyPage<MemberFamilyViews.FamilyProgramme> programmes(UUID childId, int limit,
        int offset) {
        return programmes.handle(new ListMyChildProgrammesQuery(childId, limit, offset));
    }
    @Override
    public MemberFamilyViews.FamilyPage<MemberFamilyViews.FamilySession> sessions(UUID childId, int limit, int offset) {
        return sessions.handle(new ListMyChildSessionsQuery(childId, limit, offset));
    }
    @Override
    public MemberFamilyViews.FamilyPage<MemberFamilyViews.FamilyVisit> attendance(UUID childId, int limit, int offset) {
        return attendance.handle(new ListMyChildAttendanceQuery(childId, limit, offset));
    }
    @Override
    public LabelIssue label(UUID childId, UUID attendanceId, LabelRequest body) {
        return label.handle(
            new IssueMyChildPickupLabelCommand(childId, attendanceId, body.expectedVersion(), body.replacePrevious()));
    }
}
