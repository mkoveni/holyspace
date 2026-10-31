package org.onechurch.childrenministry.sessions.presentation;
import org.onechurch.childrenministry.reporting.application.query.getchildattendance.GetChildAttendanceHandler;
import org.onechurch.childrenministry.reporting.application.query.getchildattendance.GetChildAttendanceQuery;
import org.onechurch.childrenministry.sessions.application.AttendanceView;
import org.onechurch.childrenministry.sessions.application.LabelIssue;
import org.onechurch.childrenministry.sessions.application.command.checkinchild.CheckInChildCommand;
import org.onechurch.childrenministry.sessions.application.command.checkinchild.CheckInChildHandler;
import org.onechurch.childrenministry.sessions.application.command.checkoutchild.CheckOutChildCommand;
import org.onechurch.childrenministry.sessions.application.command.checkoutchild.CheckOutChildHandler;
import org.onechurch.childrenministry.sessions.application.command.issuepickuplabel.IssuePickupLabelCommand;
import org.onechurch.childrenministry.sessions.application.command.issuepickuplabel.IssuePickupLabelHandler;
import org.onechurch.childrenministry.sessions.application.command.supervisorcheckout.SupervisorCheckoutCommand;
import org.onechurch.childrenministry.sessions.application.command.supervisorcheckout.SupervisorCheckoutHandler;
import org.onechurch.childrenministry.sessions.application.command.transferchild.TransferChildCommand;
import org.onechurch.childrenministry.sessions.application.command.transferchild.TransferChildHandler;
import org.onechurch.childrenministry.sessions.application.query.getattendance.GetAttendanceHandler;
import org.onechurch.childrenministry.sessions.application.query.getattendance.GetAttendanceQuery;
import org.onechurch.childrenministry.sessions.application.query.getattendanceevents.GetAttendanceEventsHandler;
import org.onechurch.childrenministry.sessions.application.query.getattendanceevents.GetAttendanceEventsQuery;
import org.onechurch.childrenministry.sessions.application.query.getattendancesegments.GetAttendanceSegmentsHandler;
import org.onechurch.childrenministry.sessions.application.query.getattendancesegments.GetAttendanceSegmentsQuery;
import org.onechurch.childrenministry.sessions.application.query.getexpectedchildren.ExpectedChild;
import org.onechurch.childrenministry.sessions.application.query.getexpectedchildren.GetExpectedChildrenHandler;
import org.onechurch.childrenministry.sessions.application.query.getexpectedchildren.GetExpectedChildrenQuery;
import org.onechurch.childrenministry.sessions.application.query.getpickupcollectors.GetPickupCollectorsHandler;
import org.onechurch.childrenministry.sessions.application.query.getpickupcollectors.GetPickupCollectorsQuery;
import org.onechurch.childrenministry.sessions.application.query.getpickupcollectors.PickupCollector;
import org.onechurch.childrenministry.sessions.application.query.getroster.GetRosterHandler;
import org.onechurch.childrenministry.sessions.application.query.getroster.GetRosterQuery;
import org.onechurch.childrenministry.sessions.domain.AttendanceEvent;
import org.onechurch.childrenministry.sessions.domain.AttendanceSegment;
import org.onechurch.childrenministry.sessions.presentation.api.AttendanceApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.List;
import java.util.UUID;
@RestController
public class AttendanceController implements AttendanceApi {
    private final GetExpectedChildrenHandler expectedChildren;
    private final GetPickupCollectorsHandler pickupCollectors;
    private final CheckInChildHandler checkInChild;
    private final IssuePickupLabelHandler issuePickupLabel;
    private final CheckOutChildHandler checkOutChild;
    private final SupervisorCheckoutHandler supervisorCheckout;
    private final TransferChildHandler transferChild;
    private final GetRosterHandler getRoster;
    private final GetChildAttendanceHandler getChildAttendance;
    private final GetAttendanceEventsHandler getAttendanceEvents;
    private final GetAttendanceSegmentsHandler getAttendanceSegments;
    private final GetAttendanceHandler getAttendance;
    public AttendanceController(GetExpectedChildrenHandler expectedChildren,
        GetPickupCollectorsHandler pickupCollectors, CheckInChildHandler checkInChild,
        IssuePickupLabelHandler issuePickupLabel, CheckOutChildHandler checkOutChild,
        SupervisorCheckoutHandler supervisorCheckout, TransferChildHandler transferChild, GetRosterHandler getRoster,
        GetChildAttendanceHandler getChildAttendance, GetAttendanceEventsHandler getAttendanceEvents,
        GetAttendanceSegmentsHandler getAttendanceSegments, GetAttendanceHandler getAttendance) {
        this.expectedChildren = expectedChildren;
        this.pickupCollectors = pickupCollectors;
        this.checkInChild = checkInChild;
        this.issuePickupLabel = issuePickupLabel;
        this.checkOutChild = checkOutChild;
        this.supervisorCheckout = supervisorCheckout;
        this.transferChild = transferChild;
        this.getRoster = getRoster;
        this.getChildAttendance = getChildAttendance;
        this.getAttendanceEvents = getAttendanceEvents;
        this.getAttendanceSegments = getAttendanceSegments;
        this.getAttendance = getAttendance;
    }
    @Override
    @PreAuthorize("hasAuthority('children.station')")
    public AttendanceView checkInChild(CheckInChildRequest body, OAuth2AuthenticatedPrincipal principal) {
        return checkInChild.handle(new CheckInChildCommand(UUID.fromString(principal.getName()), body.sessionId(),
            body.childId(), body.requestId()));
    }
    @Override
    @PreAuthorize("hasAuthority('children.station')")
    public LabelIssue issuePickupLabel(IssuePickupLabelRequest body, OAuth2AuthenticatedPrincipal principal) {
        return issuePickupLabel.handle(new IssuePickupLabelCommand(UUID.fromString(principal.getName()),
            body.attendanceId(), body.expectedVersion(), body.replacementReason()));
    }
    @Override
    @PreAuthorize("hasAuthority('children.station')")
    public AttendanceView checkOutChild(CheckOutChildRequest body, OAuth2AuthenticatedPrincipal principal) {
        return checkOutChild.handle(new CheckOutChildCommand(UUID.fromString(principal.getName()), body.attendanceId(),
            body.expectedVersion(), body.requestId(), body.collectorId(), body.identityVerified(), body.pickupCode()));
    }
    @Override
    @PreAuthorize("hasAuthority('children.supervise')")
    public AttendanceView supervisorCheckout(SupervisorCheckoutRequest body, OAuth2AuthenticatedPrincipal principal) {
        return supervisorCheckout
            .handle(new SupervisorCheckoutCommand(UUID.fromString(principal.getName()), body.attendanceId(),
                body.expectedVersion(), body.requestId(), body.collectorId(), body.identityVerified(), body.reason()));
    }
    @Override
    @PreAuthorize("hasAuthority('children.supervise')")
    public AttendanceView transferChild(TransferChildRequest body, OAuth2AuthenticatedPrincipal principal) {
        return transferChild.handle(new TransferChildCommand(UUID.fromString(principal.getName()), body.attendanceId(),
            body.expectedVersion(), body.targetSessionId(), body.reason()));
    }
    @Override
    @PreAuthorize("hasAuthority('children.station')")
    public List<AttendanceView> getRoster(UUID sessionId, boolean openOnly, int limit, int offset,
        OAuth2AuthenticatedPrincipal principal) {
        return getRoster
            .handle(new GetRosterQuery(UUID.fromString(principal.getName()), sessionId, openOnly, limit, offset));
    }
    @Override
    @PreAuthorize("hasAuthority('children.read')")
    public List<AttendanceView> getChildAttendance(UUID childId, int limit, int offset) {
        return getChildAttendance.handle(new GetChildAttendanceQuery(childId, limit, offset));
    }
    @Override
    @PreAuthorize("hasAuthority('children.supervise')")
    public List<AttendanceEvent> getAttendanceEvents(UUID attendanceId, int limit, int offset) {
        return getAttendanceEvents.handle(new GetAttendanceEventsQuery(attendanceId, limit, offset));
    }
    @Override
    @PreAuthorize("hasAuthority('children.read')")
    public List<AttendanceSegment> getAttendanceSegments(UUID attendanceId) {
        return getAttendanceSegments.handle(new GetAttendanceSegmentsQuery(attendanceId));
    }
    @Override
    @PreAuthorize("hasAuthority('children.station')")
    public AttendanceView getAttendance(UUID attendanceId, OAuth2AuthenticatedPrincipal principal) {
        return getAttendance.handle(new GetAttendanceQuery(UUID.fromString(principal.getName()), attendanceId));
    }

    @Override
    @PreAuthorize("hasAuthority('children.station')")
    public List<ExpectedChild> getExpectedChildren(UUID sessionId, int limit, int offset,
        OAuth2AuthenticatedPrincipal principal) {
        return expectedChildren
            .handle(new GetExpectedChildrenQuery(UUID.fromString(principal.getName()), sessionId, limit, offset));
    }
    @Override
    @PreAuthorize("hasAuthority('children.station')")
    public List<PickupCollector> getPickupCollectors(UUID attendanceId, OAuth2AuthenticatedPrincipal principal) {
        return pickupCollectors
            .handle(new GetPickupCollectorsQuery(UUID.fromString(principal.getName()), attendanceId));
    }
}
