package org.onechurch.childrenministry.sessions.infrastructure;

import org.onechurch.childrenministry.incidents.application.contract.IncidentReader;
import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.safeguarding.application.contract.ReadinessReader;
import org.onechurch.childrenministry.safeguarding.application.contract.SafeguardingReader;
import org.onechurch.childrenministry.sessions.application.AdmissionPolicy;
import org.onechurch.childrenministry.sessions.application.Handover;
import org.onechurch.childrenministry.sessions.application.ParticipantAccess;
import org.onechurch.childrenministry.sessions.application.SessionChildPreparation;
import org.onechurch.childrenministry.sessions.application.SessionOperations;
import org.onechurch.childrenministry.sessions.application.SessionSeriesPlanning;
import org.onechurch.childrenministry.sessions.application.StaffScope;
import org.onechurch.childrenministry.sessions.application.TeamPlanning;
import org.onechurch.childrenministry.sessions.application.command.applyteamplan.ApplyTeamPlanHandler;
import org.onechurch.childrenministry.sessions.application.command.cancelsessionseries.CancelSessionSeriesHandler;
import org.onechurch.childrenministry.sessions.application.command.checkinchild.CheckInChildHandler;
import org.onechurch.childrenministry.sessions.application.command.checkoutchild.CheckOutChildHandler;
import org.onechurch.childrenministry.sessions.application.command.createsession.CreateSessionHandler;
import org.onechurch.childrenministry.sessions.application.command.createsessionseries.CreateSessionSeriesHandler;
import org.onechurch.childrenministry.sessions.application.command.issuepickuplabel.IssuePickupLabelHandler;
import org.onechurch.childrenministry.sessions.application.command.registerworker.RegisterWorkerHandler;
import org.onechurch.childrenministry.sessions.application.command.reviewworker.ReviewWorkerHandler;
import org.onechurch.childrenministry.sessions.application.command.setsessionstaff.SetSessionStaffHandler;
import org.onechurch.childrenministry.sessions.application.command.setsessionstatus.SetSessionStatusHandler;
import org.onechurch.childrenministry.sessions.application.command.supervisorcheckout.SupervisorCheckoutHandler;
import org.onechurch.childrenministry.sessions.application.command.transferchild.TransferChildHandler;
import org.onechurch.childrenministry.sessions.application.contract.AttendanceReader;
import org.onechurch.childrenministry.sessions.application.contract.PickupTokens;
import org.onechurch.childrenministry.sessions.application.contract.SessionCloseoutReader;
import org.onechurch.childrenministry.sessions.application.contract.SessionDayReader;
import org.onechurch.childrenministry.sessions.application.contract.SessionReader;
import org.onechurch.childrenministry.sessions.application.contract.SessionSeriesReader;
import org.onechurch.childrenministry.sessions.application.contract.TeamPlanningReader;
import org.onechurch.childrenministry.sessions.application.contract.TeamPlanningWriter;
import org.onechurch.childrenministry.sessions.application.contract.VolunteerStaffLinks;
import org.onechurch.childrenministry.sessions.application.query.getattendance.GetAttendanceHandler;
import org.onechurch.childrenministry.sessions.application.query.getattendanceevents.GetAttendanceEventsHandler;
import org.onechurch.childrenministry.sessions.application.query.getattendancesegments.GetAttendanceSegmentsHandler;
import org.onechurch.childrenministry.sessions.application.query.getexpectedchildren.GetExpectedChildrenHandler;
import org.onechurch.childrenministry.sessions.application.query.getpickupcollectors.GetPickupCollectorsHandler;
import org.onechurch.childrenministry.sessions.application.query.getroster.GetRosterHandler;
import org.onechurch.childrenministry.sessions.application.query.getsession.GetSessionHandler;
import org.onechurch.childrenministry.sessions.application.query.getsessioncloseout.GetSessionCloseoutHandler;
import org.onechurch.childrenministry.sessions.application.query.getsessionday.GetSessionDayHandler;
import org.onechurch.childrenministry.sessions.application.query.getsessionseries.GetSessionSeriesHandler;
import org.onechurch.childrenministry.sessions.application.query.getstationroster.GetStationRosterHandler;
import org.onechurch.childrenministry.sessions.application.query.getstationsession.GetStationSessionHandler;
import org.onechurch.childrenministry.sessions.application.query.getteamplanning.GetTeamPlanningHandler;
import org.onechurch.childrenministry.sessions.application.query.getworker.GetWorkerHandler;
import org.onechurch.childrenministry.sessions.application.query.listplanningworkers.ListPlanningWorkersHandler;
import org.onechurch.childrenministry.sessions.application.query.listsessions.ListSessionsHandler;
import org.onechurch.childrenministry.sessions.application.query.listsessionseries.ListSessionSeriesHandler;
import org.onechurch.childrenministry.sessions.application.query.liststationsessions.ListStationSessionsHandler;
import org.onechurch.childrenministry.sessions.application.query.previewsessionseries.PreviewSessionSeriesHandler;
import org.onechurch.childrenministry.sessions.application.query.previewteamplan.PreviewTeamPlanHandler;
import org.onechurch.childrenministry.sessions.domain.repository.AttendanceRepository;
import org.onechurch.childrenministry.sessions.domain.repository.SessionRepository;
import org.onechurch.childrenministry.sessions.domain.repository.SessionSeriesRepository;
import org.onechurch.childrenministry.shared.application.contract.ChildLocks;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.identityaccess.application.contract.AccountDirectory;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.application.UnitOfWork;
import org.onechurch.volunteering.application.contract.StaffingDirectory;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import java.time.Clock;
import java.time.ZoneId;

@Configuration
public class ChildrenSessionsConfiguration {
    @Bean
    ParticipantAccess participantAccess(PersonDirectory people, ChildLocks locks) {
        return new ParticipantAccess(people, locks);
    }
    @Bean
    StaffScope staffScope(PermissionChecker authorization, SessionReader session) {
        return new StaffScope(authorization, session);
    }
    @Bean
    SessionOperations sessionOperations(SessionReader session, ParticipationReader participation,
        AttendanceReader attendance, PersonDirectory people, AccountDirectory accounts,
        StaffingDirectory volunteerStaffing, VolunteerStaffLinks volunteerLinks) {
        return new SessionOperations(session, participation, attendance, people, accounts, volunteerStaffing,
            volunteerLinks);
    }
    @Bean
    AdmissionPolicy admissionPolicy(ParticipationReader participation, SafeguardingReader safeguarding,
        ParticipantAccess participants, SessionOperations operations) {
        return new AdmissionPolicy(participation, safeguarding, participants, operations);
    }
    @Bean
    Handover handover(PermissionChecker authorization, ParticipantAccess participants, StaffScope scope,
        AttendanceReader attendance, AttendanceRepository attendanceRepository, SessionRepository sessionRepository,
        ParticipationReader participation, SafeguardingReader safeguarding, PickupTokens tokens, ChildrenAudit audit,
        UnitOfWork transactions, Clock clock, ZoneId zone) {
        return new Handover(authorization, participants, scope, attendance, attendanceRepository, sessionRepository,
            participation, safeguarding, tokens, audit, transactions, clock, zone);
    }
    @Bean
    RegisterWorkerHandler registerWorkerHandler(PermissionChecker authorization, ChildrenAudit audit,
        UnitOfWork transactions, Clock clock, ParticipantAccess participants, AccountDirectory accounts,
        SessionRepository sessionRepository, ZoneId zone) {
        return new RegisterWorkerHandler(authorization, audit, transactions, clock, participants, accounts,
            sessionRepository, zone);
    }
    @Bean
    ReviewWorkerHandler reviewWorkerHandler(PermissionChecker authorization, ChildrenAudit audit,
        UnitOfWork transactions, Clock clock, SessionRepository sessionRepository, ParticipantAccess participants,
        ZoneId zone, AccountDirectory accounts) {
        return new ReviewWorkerHandler(authorization, audit, transactions, clock, sessionRepository, participants, zone,
            accounts);
    }
    @Bean
    CreateSessionHandler createSessionHandler(PermissionChecker authorization, ChildrenAudit audit,
        UnitOfWork transactions, Clock clock, ParticipationReader participation, SessionReader session,
        SessionRepository sessionRepository) {
        return new CreateSessionHandler(authorization, audit, transactions, clock, participation, session,
            sessionRepository);
    }
    @Bean
    SetSessionStaffHandler setSessionStaffHandler(PermissionChecker authorization, ChildrenAudit audit,
        UnitOfWork transactions, Clock clock, SessionRepository sessionRepository, SessionReader session,
        SessionOperations operations) {
        return new SetSessionStaffHandler(authorization, audit, transactions, clock, sessionRepository, session,
            operations);
    }
    @Bean
    SetSessionStatusHandler setSessionStatusHandler(PermissionChecker authorization, ChildrenAudit audit,
        UnitOfWork transactions, Clock clock, SessionRepository sessionRepository, SessionOperations operations,
        AttendanceReader attendance, IncidentReader incidents, ZoneId zone) {
        return new SetSessionStatusHandler(authorization, audit, transactions, clock, sessionRepository, operations,
            attendance, incidents, zone);
    }
    @Bean
    CheckInChildHandler checkInChildHandler(PermissionChecker authorization, ChildrenAudit audit,
        UnitOfWork transactions, Clock clock, ParticipantAccess participants, StaffScope scope,
        SessionRepository sessionRepository, AttendanceReader attendance, AttendanceRepository attendanceRepository,
        SessionOperations operations, AdmissionPolicy admission) {
        return new CheckInChildHandler(authorization, audit, transactions, clock, participants, scope,
            sessionRepository, attendance, attendanceRepository, operations, admission);
    }
    @Bean
    IssuePickupLabelHandler issuePickupLabelHandler(PermissionChecker authorization, ChildrenAudit audit,
        UnitOfWork transactions, Clock clock, ParticipantAccess participants, StaffScope scope,
        SessionRepository sessionRepository, AttendanceReader attendance, AttendanceRepository attendanceRepository,
        PickupTokens tokens) {
        return new IssuePickupLabelHandler(authorization, audit, transactions, clock, participants, scope,
            sessionRepository, attendance, attendanceRepository, tokens);
    }
    @Bean
    CheckOutChildHandler checkOutChildHandler(PermissionChecker authorization, Handover handover) {
        return new CheckOutChildHandler(authorization, handover);
    }
    @Bean
    SupervisorCheckoutHandler supervisorCheckoutHandler(PermissionChecker authorization, Handover handover) {
        return new SupervisorCheckoutHandler(authorization, handover);
    }
    @Bean
    TransferChildHandler transferChildHandler(PermissionChecker authorization, ChildrenAudit audit,
        UnitOfWork transactions, Clock clock, ParticipantAccess participants, SessionRepository sessionRepository,
        AttendanceReader attendance, AttendanceRepository attendanceRepository, SessionOperations operations,
        AdmissionPolicy admission) {
        return new TransferChildHandler(authorization, audit, transactions, clock, participants, sessionRepository,
            attendance, attendanceRepository, operations, admission);
    }
    @Bean
    ListSessionsHandler listSessionsHandler(PermissionChecker authorization, SessionReader session,
        SessionOperations operations, Clock clock) {
        return new ListSessionsHandler(authorization, session, operations, clock);
    }
    @Bean
    GetSessionHandler getSessionHandler(PermissionChecker authorization, SessionReader session,
        SessionOperations operations, Clock clock) {
        return new GetSessionHandler(authorization, session, operations, clock);
    }
    @Bean
    GetRosterHandler getRosterHandler(PermissionChecker authorization, StaffScope scope, AttendanceReader attendance) {
        return new GetRosterHandler(authorization, scope, attendance);
    }
    @Bean
    GetAttendanceEventsHandler getAttendanceEventsHandler(PermissionChecker authorization,
        AttendanceReader attendance) {
        return new GetAttendanceEventsHandler(authorization, attendance);
    }
    @Bean
    GetAttendanceSegmentsHandler getAttendanceSegmentsHandler(PermissionChecker authorization,
        AttendanceReader attendance) {
        return new GetAttendanceSegmentsHandler(authorization, attendance);
    }
    @Bean
    GetWorkerHandler getWorkerHandler(PermissionChecker authorization, SessionReader session) {
        return new GetWorkerHandler(authorization, session);
    }
    @Bean
    GetAttendanceHandler getAttendanceHandler(PermissionChecker authorization, AttendanceReader attendance,
        StaffScope scope) {
        return new GetAttendanceHandler(authorization, attendance, scope);
    }
    @Bean
    GetExpectedChildrenHandler getExpectedChildrenHandler(PermissionChecker authorization, StaffScope scope,
        SessionReader sessions, ParticipationReader participation, SessionOperations operations,
        ParticipantAccess people) {
        return new GetExpectedChildrenHandler(authorization, scope, sessions, participation, operations, people);
    }
    @Bean
    GetPickupCollectorsHandler getPickupCollectorsHandler(PermissionChecker authorization, StaffScope scope,
        AttendanceReader attendance, SessionReader sessions, ParticipationReader participation,
        SafeguardingReader safeguarding, ParticipantAccess people, Clock clock, ZoneId zone) {
        return new GetPickupCollectorsHandler(authorization, scope, attendance, sessions, participation, safeguarding,
            people, clock, zone);
    }
    @Bean
    ListStationSessionsHandler listStationSessionsHandler(PermissionChecker authorization, SessionReader sessions,
        SessionOperations operations, Clock clock) {
        return new ListStationSessionsHandler(authorization, sessions, operations, clock);
    }
    @Bean
    GetStationSessionHandler getStationSessionHandler(PermissionChecker authorization, SessionReader sessions,
        SessionOperations operations, StaffScope scope, Clock clock) {
        return new GetStationSessionHandler(authorization, sessions, operations, scope, clock);
    }
    @Bean
    GetStationRosterHandler getStationRosterHandler(PermissionChecker authorization, StaffScope scope,
        AttendanceReader attendance, PersonDirectory people) {
        return new GetStationRosterHandler(authorization, scope, attendance, people);
    }
    @Bean
    SessionChildPreparation sessionChildPreparation(ReadinessReader reader, ParticipationReader participation,
        SessionOperations operations) {
        return new SessionChildPreparation(reader, participation, operations);
    }
    @Bean
    GetSessionDayHandler getSessionDayHandler(PermissionChecker authorization, StaffScope scope, SessionReader sessions,
        SessionDayReader reader, PersonDirectory people, SessionOperations operations,
        SessionChildPreparation preparation, Clock clock) {
        return new GetSessionDayHandler(authorization, scope, sessions, reader, people, operations, preparation, clock);
    }
    @Bean
    GetSessionCloseoutHandler getSessionCloseoutHandler(PermissionChecker authorization, SessionReader sessions,
        SessionCloseoutReader reader, Clock clock, IncidentReader incidents, ZoneId zone) {
        return new GetSessionCloseoutHandler(authorization, sessions, reader, clock, incidents, zone);
    }
    @Bean
    SessionSeriesPlanning sessionSeriesPlanning(SessionReader sessions, SessionSeriesReader series,
        ParticipationReader participation, PersonDirectory people, AccountDirectory accounts, Clock clock) {
        return new SessionSeriesPlanning(sessions, series, participation, people, accounts, clock);
    }
    @Bean
    PreviewSessionSeriesHandler previewSessionSeriesHandler(PermissionChecker authorization,
        SessionSeriesPlanning planning) {
        return new PreviewSessionSeriesHandler(authorization, planning);
    }
    @Bean
    ListSessionSeriesHandler listSessionSeriesHandler(PermissionChecker authorization, SessionSeriesReader reader) {
        return new ListSessionSeriesHandler(authorization, reader);
    }
    @Bean
    GetSessionSeriesHandler getSessionSeriesHandler(PermissionChecker authorization, SessionSeriesReader reader) {
        return new GetSessionSeriesHandler(authorization, reader);
    }
    @Bean
    CreateSessionSeriesHandler createSessionSeriesHandler(PermissionChecker authorization, UnitOfWork transactions,
        SessionSeriesRepository series, SessionRepository sessions, SessionSeriesPlanning planning, ChildrenAudit audit,
        Clock clock) {
        return new CreateSessionSeriesHandler(authorization, transactions, series, sessions, planning, audit, clock);
    }
    @Bean
    CancelSessionSeriesHandler cancelSessionSeriesHandler(PermissionChecker authorization, UnitOfWork transactions,
        SessionSeriesRepository series, SessionRepository sessions, AttendanceReader attendance, ChildrenAudit audit,
        Clock clock) {
        return new CancelSessionSeriesHandler(authorization, transactions, series, sessions, attendance, audit, clock);
    }
    @Bean
    TeamPlanning teamPlanning(TeamPlanningReader reader, PersonDirectory people, AccountDirectory accounts, Clock clock,
        ZoneId zone) {
        return new TeamPlanning(reader, people, accounts, clock, zone);
    }
    @Bean
    GetTeamPlanningHandler getTeamPlanningHandler(PermissionChecker authorization, TeamPlanning planning) {
        return new GetTeamPlanningHandler(authorization, planning);
    }
    @Bean
    ListPlanningWorkersHandler listPlanningWorkersHandler(PermissionChecker authorization, TeamPlanning planning) {
        return new ListPlanningWorkersHandler(authorization, planning);
    }
    @Bean
    PreviewTeamPlanHandler previewTeamPlanHandler(PermissionChecker authorization, TeamPlanning planning) {
        return new PreviewTeamPlanHandler(authorization, planning);
    }
    @Bean
    ApplyTeamPlanHandler applyTeamPlanHandler(PermissionChecker authorization, TeamPlanning planning,
        SessionRepository sessions, TeamPlanningWriter writer, UnitOfWork transactions, ChildrenAudit audit,
        Clock clock) {
        return new ApplyTeamPlanHandler(authorization, planning, sessions, writer, transactions, audit, clock);
    }
}
