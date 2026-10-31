package org.onechurch.childrenministry.participation.infrastructure;
import org.onechurch.childrenministry.participation.application.command.assignclass.AssignClassHandler;
import org.onechurch.childrenministry.participation.application.command.createclass.CreateClassHandler;
import org.onechurch.childrenministry.participation.application.command.createlocation.CreateLocationHandler;
import org.onechurch.childrenministry.participation.application.command.createprogramme.CreateProgrammeHandler;
import org.onechurch.childrenministry.participation.application.command.createroom.CreateRoomHandler;
import org.onechurch.childrenministry.participation.application.command.endclassassignment.EndClassAssignmentHandler;
import org.onechurch.childrenministry.participation.application.command.endenrolment.EndEnrolmentHandler;
import org.onechurch.childrenministry.participation.application.command.enrolchild.EnrolChildHandler;
import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.participation.application.contract.ProgrammeRosterReader;
import org.onechurch.childrenministry.participation.application.query.getprogramme.GetProgrammeHandler;
import org.onechurch.childrenministry.participation.application.query.listclassassignments.ListClassAssignmentsHandler;
import org.onechurch.childrenministry.participation.application.query.listclasses.ListClassesHandler;
import org.onechurch.childrenministry.participation.application.query.listenrolments.ListEnrolmentsHandler;
import org.onechurch.childrenministry.participation.application.query.listlocations.ListLocationsHandler;
import org.onechurch.childrenministry.participation.application.query.listprogrammechildren.ListProgrammeChildrenHandler;
import org.onechurch.childrenministry.participation.application.query.listprogrammes.ListProgrammesHandler;
import org.onechurch.childrenministry.participation.application.query.listrooms.ListRoomsHandler;
import org.onechurch.childrenministry.participation.domain.repository.ParticipationRepository;
import org.onechurch.childrenministry.safeguarding.application.ChildReadiness;
import org.onechurch.childrenministry.sessions.application.ParticipantAccess;
import org.onechurch.childrenministry.sessions.application.contract.SessionReader;
import org.onechurch.childrenministry.sessions.domain.repository.SessionRepository;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.application.UnitOfWork;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import java.time.Clock;
import java.time.ZoneId;
@Configuration
public class ChildrenParticipationConfiguration {
    @Bean
    CreateProgrammeHandler createProgrammeHandler(PermissionChecker authorization, ChildrenAudit audit,
        UnitOfWork transactions, Clock clock, ParticipationRepository participationRepository) {
        return new CreateProgrammeHandler(authorization, audit, transactions, clock, participationRepository);
    }
    @Bean
    CreateClassHandler createClassHandler(PermissionChecker authorization, ChildrenAudit audit, UnitOfWork transactions,
        Clock clock, ParticipationRepository participationRepository, ParticipationReader participation) {
        return new CreateClassHandler(authorization, audit, transactions, clock, participationRepository,
            participation);
    }
    @Bean
    EnrolChildHandler enrolChildHandler(PermissionChecker authorization, ChildrenAudit audit, UnitOfWork transactions,
        Clock clock, ParticipationRepository participationRepository, ParticipationReader participation,
        ParticipantAccess participants, ZoneId zone) {
        return new EnrolChildHandler(authorization, audit, transactions, clock, participationRepository, participation,
            participants, zone);
    }
    @Bean
    AssignClassHandler assignClassHandler(PermissionChecker authorization, ChildrenAudit audit, UnitOfWork transactions,
        Clock clock, ParticipationReader participation, ParticipationRepository participationRepository,
        ParticipantAccess participants) {
        return new AssignClassHandler(authorization, audit, transactions, clock, participation, participationRepository,
            participants);
    }
    @Bean
    EndEnrolmentHandler endEnrolmentHandler(PermissionChecker authorization, ChildrenAudit audit,
        UnitOfWork transactions, Clock clock, ParticipationReader participation,
        ParticipationRepository participationRepository, ParticipantAccess participants) {
        return new EndEnrolmentHandler(authorization, audit, transactions, clock, participation,
            participationRepository, participants);
    }
    @Bean
    EndClassAssignmentHandler endClassAssignmentHandler(PermissionChecker authorization, ChildrenAudit audit,
        UnitOfWork transactions, Clock clock, ParticipationReader participation,
        ParticipationRepository participationRepository, ParticipantAccess participants) {
        return new EndClassAssignmentHandler(authorization, audit, transactions, clock, participation,
            participationRepository, participants);
    }
    @Bean
    CreateLocationHandler createLocationHandler(PermissionChecker authorization, ChildrenAudit audit,
        UnitOfWork transactions, Clock clock, SessionRepository sessionRepository) {
        return new CreateLocationHandler(authorization, audit, transactions, clock, sessionRepository);
    }
    @Bean
    CreateRoomHandler createRoomHandler(PermissionChecker authorization, ChildrenAudit audit, UnitOfWork transactions,
        Clock clock, SessionReader session, SessionRepository sessionRepository) {
        return new CreateRoomHandler(authorization, audit, transactions, clock, session, sessionRepository);
    }
    @Bean
    ListProgrammesHandler listProgrammesHandler(PermissionChecker authorization, ParticipationReader participation) {
        return new ListProgrammesHandler(authorization, participation);
    }
    @Bean
    GetProgrammeHandler getProgrammeHandler(PermissionChecker authorization, ParticipationReader participation) {
        return new GetProgrammeHandler(authorization, participation);
    }
    @Bean
    ListClassesHandler listClassesHandler(PermissionChecker authorization, ParticipationReader participation) {
        return new ListClassesHandler(authorization, participation);
    }
    @Bean
    ListEnrolmentsHandler listEnrolmentsHandler(PermissionChecker authorization, ParticipationReader participation) {
        return new ListEnrolmentsHandler(authorization, participation);
    }
    @Bean
    ListClassAssignmentsHandler listClassAssignmentsHandler(PermissionChecker authorization,
        ParticipationReader participation) {
        return new ListClassAssignmentsHandler(authorization, participation);
    }
    @Bean
    ListRoomsHandler listRoomsHandler(PermissionChecker authorization, SessionReader session) {
        return new ListRoomsHandler(authorization, session);
    }
    @Bean
    ListLocationsHandler listLocationsHandler(PermissionChecker authorization, SessionReader session) {
        return new ListLocationsHandler(authorization, session);
    }
    @Bean
    ListProgrammeChildrenHandler listProgrammeChildrenHandler(PermissionChecker authorization,
        ParticipationReader participation, ProgrammeRosterReader roster, PersonDirectory people, Clock clock,
        ZoneId zone, ChildReadiness readiness) {
        return new ListProgrammeChildrenHandler(authorization, participation, roster, people, clock, zone, readiness);
    }
}
