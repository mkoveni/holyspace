package org.onechurch.childrenministry.sessions.infrastructure;
import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.safeguarding.application.ChildReadiness;
import org.onechurch.childrenministry.safeguarding.application.contract.SafeguardingReader;
import org.onechurch.childrenministry.sessions.application.MemberFamilyViews;
import org.onechurch.childrenministry.sessions.application.ParticipantAccess;
import org.onechurch.childrenministry.sessions.application.SessionChildPreparation;
import org.onechurch.childrenministry.sessions.application.SessionOperations;
import org.onechurch.childrenministry.sessions.application.StaffScope;
import org.onechurch.childrenministry.sessions.application.command.issuemychildpickuplabel.IssueMyChildPickupLabelHandler;
import org.onechurch.childrenministry.sessions.application.contract.AttendanceReader;
import org.onechurch.childrenministry.sessions.application.contract.MemberFamilyReader;
import org.onechurch.childrenministry.sessions.application.contract.PickupTokens;
import org.onechurch.childrenministry.sessions.application.contract.SessionReader;
import org.onechurch.childrenministry.sessions.application.query.getfamilyarrivalreference.GetFamilyArrivalReferenceHandler;
import org.onechurch.childrenministry.sessions.application.query.getmychild.GetMyChildHandler;
import org.onechurch.childrenministry.sessions.application.query.listmychildattendance.ListMyChildAttendanceHandler;
import org.onechurch.childrenministry.sessions.application.query.listmychildprogrammes.ListMyChildProgrammesHandler;
import org.onechurch.childrenministry.sessions.application.query.listmychildren.ListMyChildrenHandler;
import org.onechurch.childrenministry.sessions.application.query.listmychildsessions.ListMyChildSessionsHandler;
import org.onechurch.childrenministry.sessions.domain.repository.AttendanceRepository;
import org.onechurch.childrenministry.sessions.domain.repository.SessionRepository;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.identityaccess.application.contract.AuthenticatedAccount;
import org.onechurch.identityaccess.application.contract.MemberIdentity;
import org.onechurch.identityaccess.application.contract.MemberIdentityGuard;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.application.UnitOfWork;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import java.time.Clock;
import java.time.ZoneId;
@Configuration
public class MemberFamilyConfiguration {
    @Bean
    MemberFamilyViews memberFamilyViews(MemberFamilyReader reader, PersonDirectory people,
        ParticipationReader participation, ChildReadiness readiness, SessionChildPreparation preparation,
        SafeguardingReader safeguarding, SessionReader sessions, Clock clock, ZoneId zone) {
        return new MemberFamilyViews(reader, people, participation, readiness, preparation, safeguarding, sessions,
            clock, zone);
    }
    @Bean
    IssueMyChildPickupLabelHandler issueMyChildPickupLabel(AuthenticatedAccount account, MemberIdentity identity,
        MemberIdentityGuard guard, MemberFamilyViews views, ParticipantAccess participants, AttendanceReader attendance,
        AttendanceRepository repository, SessionRepository sessions, ParticipationReader participation,
        SafeguardingReader safeguarding, PickupTokens tokens, ChildrenAudit audit, UnitOfWork tx, Clock clock) {
        return new IssueMyChildPickupLabelHandler(account, identity, guard, views, participants, attendance, repository,
            sessions, participation, safeguarding, tokens, audit, tx, clock);
    }
    @Bean
    ListMyChildrenHandler listmychildren(MemberIdentity identity, AuthenticatedAccount account,
        MemberFamilyViews views) {
        return new ListMyChildrenHandler(identity, account, views);
    }
    @Bean
    GetMyChildHandler getmychild(MemberIdentity identity, AuthenticatedAccount account, MemberFamilyViews views) {
        return new GetMyChildHandler(identity, account, views);
    }
    @Bean
    ListMyChildProgrammesHandler listmychildprogrammes(MemberIdentity identity, AuthenticatedAccount account,
        MemberFamilyViews views) {
        return new ListMyChildProgrammesHandler(identity, account, views);
    }
    @Bean
    ListMyChildSessionsHandler listmychildsessions(MemberIdentity identity, AuthenticatedAccount account,
        MemberFamilyViews views) {
        return new ListMyChildSessionsHandler(identity, account, views);
    }
    @Bean
    ListMyChildAttendanceHandler listmychildattendance(MemberIdentity identity, AuthenticatedAccount account,
        MemberFamilyViews views) {
        return new ListMyChildAttendanceHandler(identity, account, views);
    }
    @Bean
    GetFamilyArrivalReferenceHandler getFamilyArrivalReference(AuthenticatedAccount account, PermissionChecker auth,
        StaffScope scope, SessionReader sessions, ParticipationReader participation, PersonDirectory people,
        SessionChildPreparation preparation, SessionOperations operations, Clock clock) {
        return new GetFamilyArrivalReferenceHandler(account, auth, scope, sessions, participation, people, preparation,
            operations, clock);
    }
}
