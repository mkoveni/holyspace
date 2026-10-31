package org.onechurch.childrenministry.incidents.infrastructure;
import org.onechurch.childrenministry.incidents.application.IncidentAccess;
import org.onechurch.childrenministry.incidents.application.command.accessincident.AccessIncidentHandler;
import org.onechurch.childrenministry.incidents.application.command.followupincident.FollowUpIncidentHandler;
import org.onechurch.childrenministry.incidents.application.command.purgeexpiredincidents.PurgeExpiredIncidentsHandler;
import org.onechurch.childrenministry.incidents.application.command.reportincident.ReportIncidentHandler;
import org.onechurch.childrenministry.incidents.application.contract.IncidentReader;
import org.onechurch.childrenministry.incidents.application.query.listincidentattendance.ListIncidentAttendanceHandler;
import org.onechurch.childrenministry.incidents.application.query.listincidentguardians.ListIncidentGuardiansHandler;
import org.onechurch.childrenministry.incidents.application.query.listincidentowners.ListIncidentOwnersHandler;
import org.onechurch.childrenministry.incidents.application.query.listincidents.ListIncidentsHandler;
import org.onechurch.childrenministry.incidents.domain.repository.IncidentRepository;
import org.onechurch.childrenministry.safeguarding.application.contract.SafeguardingReader;
import org.onechurch.childrenministry.safeguarding.domain.repository.SafeguardingRepository;
import org.onechurch.childrenministry.sessions.application.StaffScope;
import org.onechurch.childrenministry.sessions.domain.repository.SessionRepository;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.identityaccess.application.contract.ResponsibleAccountDirectory;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.application.UnitOfWork;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import java.time.Clock;
import java.time.ZoneId;
@Configuration
public class ChildrenIncidentsConfiguration {
    @Bean
    ListIncidentGuardiansHandler listIncidentGuardiansHandler(PermissionChecker auth, IncidentAccess access,
        SafeguardingReader safeguarding, PersonDirectory people, Clock clock, ZoneId zone) {
        return new ListIncidentGuardiansHandler(auth, access, safeguarding, people, clock, zone);
    }
    @Bean
    ListIncidentAttendanceHandler listIncidentAttendanceHandler(PermissionChecker auth, StaffScope scope,
        IncidentReader reader, PersonDirectory people) {
        return new ListIncidentAttendanceHandler(auth, scope, reader, people);
    }
    @Bean
    IncidentAccess incidentAccess(IncidentReader reader) {
        return new IncidentAccess(reader);
    }
    @Bean
    ReportIncidentHandler reportIncidentHandler(PermissionChecker auth, StaffScope scope, IncidentReader reader,
        IncidentRepository repository, SessionRepository sessions, PersonDirectory people, UnitOfWork tx,
        ChildrenAudit audit, Clock clock, ZoneId zone) {
        return new ReportIncidentHandler(auth, scope, reader, repository, sessions, people, tx, audit, clock, zone);
    }
    @Bean
    AccessIncidentHandler accessIncidentHandler(PermissionChecker auth, IncidentReader reader, IncidentAccess access,
        UnitOfWork tx, ChildrenAudit audit, Clock clock, ZoneId zone) {
        return new AccessIncidentHandler(auth, reader, access, tx, audit, clock, zone);
    }
    @Bean
    FollowUpIncidentHandler followUpIncidentHandler(PermissionChecker auth, IncidentRepository repository,
        SafeguardingReader safeguarding, ResponsibleAccountDirectory accounts, PersonDirectory people, UnitOfWork tx,
        ChildrenAudit audit, Clock clock, ZoneId zone, SafeguardingRepository guardianLocks) {
        return new FollowUpIncidentHandler(auth, repository, safeguarding, accounts, people, tx, audit, clock, zone,
            guardianLocks);
    }
    @Bean
    PurgeExpiredIncidentsHandler purgeExpiredIncidentsHandler(PermissionChecker auth, IncidentRepository repository,
        UnitOfWork tx, ChildrenAudit audit, Clock clock, ZoneId zone) {
        return new PurgeExpiredIncidentsHandler(auth, repository, tx, audit, clock, zone);
    }
    @Bean
    ListIncidentsHandler listIncidentsHandler(PermissionChecker auth, IncidentReader reader, PersonDirectory people,
        Clock clock, ZoneId zone) {
        return new ListIncidentsHandler(auth, reader, people, clock, zone);
    }
    @Bean
    ListIncidentOwnersHandler listIncidentOwnersHandler(PermissionChecker auth, ResponsibleAccountDirectory accounts) {
        return new ListIncidentOwnersHandler(auth, accounts);
    }
}
