package org.onechurch.childrenministry.incidents.presentation;
import org.onechurch.childrenministry.incidents.application.command.accessincident.AccessIncidentCommand;
import org.onechurch.childrenministry.incidents.application.command.accessincident.AccessIncidentHandler;
import org.onechurch.childrenministry.incidents.application.command.followupincident.FollowUpIncidentCommand;
import org.onechurch.childrenministry.incidents.application.command.followupincident.FollowUpIncidentHandler;
import org.onechurch.childrenministry.incidents.application.command.purgeexpiredincidents.PurgeExpiredIncidentsCommand;
import org.onechurch.childrenministry.incidents.application.command.purgeexpiredincidents.PurgeExpiredIncidentsHandler;
import org.onechurch.childrenministry.incidents.application.command.reportincident.ReportIncidentCommand;
import org.onechurch.childrenministry.incidents.application.command.reportincident.ReportIncidentHandler;
import org.onechurch.childrenministry.incidents.application.query.listincidentattendance.ListIncidentAttendanceHandler;
import org.onechurch.childrenministry.incidents.application.query.listincidentattendance.ListIncidentAttendanceQuery;
import org.onechurch.childrenministry.incidents.application.query.listincidentguardians.ListIncidentGuardiansHandler;
import org.onechurch.childrenministry.incidents.application.query.listincidentguardians.ListIncidentGuardiansQuery;
import org.onechurch.childrenministry.incidents.application.query.listincidentowners.ListIncidentOwnersHandler;
import org.onechurch.childrenministry.incidents.application.query.listincidentowners.ListIncidentOwnersQuery;
import org.onechurch.childrenministry.incidents.application.query.listincidents.ListIncidentsHandler;
import org.onechurch.childrenministry.incidents.application.query.listincidents.ListIncidentsQuery;
import org.onechurch.childrenministry.incidents.presentation.api.IncidentApi;
import org.onechurch.identityaccess.application.contract.ResponsibleAccountDirectory;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.List;
import java.util.UUID;
@RestController
public class IncidentController implements IncidentApi {
    private final ListIncidentAttendanceHandler attendance;
    private final ListIncidentGuardiansHandler guardians;
    private final ReportIncidentHandler report;
    private final AccessIncidentHandler access;
    private final FollowUpIncidentHandler followup;
    private final ListIncidentsHandler list;
    private final ListIncidentOwnersHandler owners;
    private final PurgeExpiredIncidentsHandler purge;
    public IncidentController(ReportIncidentHandler report, AccessIncidentHandler access,
        FollowUpIncidentHandler followup, ListIncidentsHandler list, ListIncidentOwnersHandler owners,
        PurgeExpiredIncidentsHandler purge, ListIncidentAttendanceHandler attendance,
        ListIncidentGuardiansHandler guardians) {
        this.guardians = guardians;
        this.attendance = attendance;
        this.report = report;
        this.access = access;
        this.followup = followup;
        this.list = list;
        this.owners = owners;
        this.purge = purge;
    }
    @Override
    @PreAuthorize("hasAnyAuthority('children.incidents.record','children.incidents.manage')")
    public Created report(Report b, OAuth2AuthenticatedPrincipal p) {
        return new Created(report.handle(new ReportIncidentCommand(UUID.fromString(p.getName()), b.requestId(),
            b.childId(), b.sessionId(), b.segmentId(), b.category(), b.severity(), b.occurredAt(), b.description(),
            b.immediateAction(), b.reviewOn(), b.retainUntil())));
    }
    @Override
    @PreAuthorize("hasAuthority('children.incidents.manage')")
    public ListIncidentsHandler.View list(UUID sessionId, UUID childId, String status, boolean overdueOnly, int limit,
        int offset) {
        return list.handle(new ListIncidentsQuery(sessionId, childId, status, overdueOnly, limit, offset));
    }
    @Override
    @PreAuthorize("hasAuthority('children.incidents.manage')")
    public AccessIncidentHandler.View access(UUID id, Access b, OAuth2AuthenticatedPrincipal p) {
        return access.handle(new AccessIncidentCommand(UUID.fromString(p.getName()), id, b.limit(), b.offset()));
    }
    @Override
    @PreAuthorize("hasAuthority('children.incidents.manage')")
    public void followUp(UUID id, FollowUp b, OAuth2AuthenticatedPrincipal p) {
        followup.handle(new FollowUpIncidentCommand(UUID.fromString(p.getName()), id, b.expectedVersion(), b.action(),
            b.ownerId(), b.reviewOn(), b.guardianAuthorityId(), b.note()));
    }
    @Override
    @PreAuthorize("hasAuthority('children.incidents.manage')")
    public List<ResponsibleAccountDirectory.Entry> owners(String search) {
        return owners.handle(new ListIncidentOwnersQuery(search));
    }
    @Override
    @PreAuthorize("hasAuthority('children.incidents.manage')")
    public Purged purge(Purge b, OAuth2AuthenticatedPrincipal p) {
        return new Purged(purge.handle(new PurgeExpiredIncidentsCommand(UUID.fromString(p.getName()), b.limit())));
    }
    @Override
    @PreAuthorize("hasAnyAuthority('children.incidents.record','children.incidents.manage')")
    public ListIncidentAttendanceHandler.View attendance(UUID sessionId, String search, int limit, int offset,
        OAuth2AuthenticatedPrincipal p) {
        return attendance
            .handle(new ListIncidentAttendanceQuery(UUID.fromString(p.getName()), sessionId, search, limit, offset));
    }

    @Override
    @PreAuthorize("hasAuthority('children.incidents.manage')")
    public List<ListIncidentGuardiansHandler.Guardian> guardians(UUID id) {
        return guardians.handle(new ListIncidentGuardiansQuery(id));
    }

}
