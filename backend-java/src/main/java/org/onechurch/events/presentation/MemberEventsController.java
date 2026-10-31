package org.onechurch.events.presentation;
import org.onechurch.events.application.MemberEventActions;
import org.onechurch.events.application.command.cancelmyevent.CancelMyEventCommand;
import org.onechurch.events.application.command.cancelmyevent.CancelMyEventHandler;
import org.onechurch.events.application.command.configurememberevent.ConfigureMemberEventCommand;
import org.onechurch.events.application.command.configurememberevent.ConfigureMemberEventHandler;
import org.onechurch.events.application.command.issuemyeventpass.IssueMyEventPassCommand;
import org.onechurch.events.application.command.issuemyeventpass.IssueMyEventPassHandler;
import org.onechurch.events.application.command.registermyevent.RegisterMyEventCommand;
import org.onechurch.events.application.command.registermyevent.RegisterMyEventHandler;
import org.onechurch.events.application.command.scanmemberattendance.ScanMemberAttendanceCommand;
import org.onechurch.events.application.command.scanmemberattendance.ScanMemberAttendanceHandler;
import org.onechurch.events.application.contract.MemberEventReader;
import org.onechurch.events.application.query.getmemberoffer.GetMemberOfferHandler;
import org.onechurch.events.application.query.getmemberoffer.GetMemberOfferQuery;
import org.onechurch.events.application.query.getmyevent.GetMyEventHandler;
import org.onechurch.events.application.query.getmyevent.GetMyEventQuery;
import org.onechurch.events.application.query.listmyevents.ListMyEventsHandler;
import org.onechurch.events.application.query.listmyevents.ListMyEventsQuery;
import org.onechurch.events.presentation.api.MemberEventsApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.ModelAttribute;
import org.springframework.web.bind.annotation.RestController;

import jakarta.servlet.http.HttpServletResponse;

import java.util.UUID;
@RestController
@PreAuthorize("isAuthenticated()")
public class MemberEventsController implements MemberEventsApi {
    private final ListMyEventsHandler list;
    private final GetMyEventHandler detail;
    private final RegisterMyEventHandler register;
    private final CancelMyEventHandler cancel;
    private final IssueMyEventPassHandler pass;
    private final ScanMemberAttendanceHandler scan;
    private final ConfigureMemberEventHandler publish;
    private final GetMemberOfferHandler offer;
    public MemberEventsController(ListMyEventsHandler list, GetMyEventHandler detail, RegisterMyEventHandler register,
        CancelMyEventHandler cancel, IssueMyEventPassHandler pass, ScanMemberAttendanceHandler scan,
        ConfigureMemberEventHandler publish, GetMemberOfferHandler offer) {
        this.list = list;
        this.detail = detail;
        this.register = register;
        this.cancel = cancel;
        this.pass = pass;
        this.scan = scan;
        this.publish = publish;
        this.offer = offer;
    }
    @ModelAttribute
    void privateResponses(HttpServletResponse response) {
        response.setHeader("Cache-Control", "no-store");
    }
    @Override
    public MemberEventReader.Page list(boolean mine, int limit, int offset) {
        return list.handle(new ListMyEventsQuery(mine, limit, offset));
    }
    @Override
    public MemberEventReader.Item detail(UUID id) {
        return detail.handle(new GetMyEventQuery(id));
    }
    @Override
    public Created register(UUID id, Register body, OAuth2AuthenticatedPrincipal principal) {
        return new Created(register.handle(new RegisterMyEventCommand(UUID.fromString(principal.getName()),
            body.requestId(), id, body.expectedVersion())));
    }
    @Override
    public void cancel(UUID id, Cancel body, OAuth2AuthenticatedPrincipal principal) {
        cancel.handle(new CancelMyEventCommand(UUID.fromString(principal.getName()), id, body.expectedVersion()));
    }
    @Override
    public MemberEventActions.Pass pass(UUID id, OAuth2AuthenticatedPrincipal principal) {
        return pass.handle(new IssueMyEventPassCommand(UUID.fromString(principal.getName()), id));
    }
    @Override
    @PreAuthorize("hasAuthority('events.attendance.manage')")
    public Created scan(UUID id, Scan body, OAuth2AuthenticatedPrincipal principal) {
        return new Created(scan.handle(new ScanMemberAttendanceCommand(UUID.fromString(principal.getName()), id,
            body.requestId(), body.credential())));
    }
    @Override
    @PreAuthorize("hasAuthority('events.manage')")
    public MemberEventReader.Offer offer(UUID id) {
        return offer.handle(new GetMemberOfferQuery(id));
    }
    @Override
    @PreAuthorize("hasAuthority('events.manage')")
    public void publish(UUID id, Publish body, OAuth2AuthenticatedPrincipal principal) {
        publish.handle(new ConfigureMemberEventCommand(UUID.fromString(principal.getName()), id, body.enabled(),
            body.expectedVersion()));
    }
}
