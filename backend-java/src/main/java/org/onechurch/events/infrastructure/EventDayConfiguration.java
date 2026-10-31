package org.onechurch.events.infrastructure;
import org.onechurch.communications.application.contract.DeliveryReader;
import org.onechurch.communications.application.contract.EmailDeliveries;
import org.onechurch.communications.application.contract.EmailTemplates;
import org.onechurch.events.application.AttendanceViews;
import org.onechurch.events.application.EventAttendanceOperations;
import org.onechurch.events.application.EventCloseouts;
import org.onechurch.events.application.EventMessageApproval;
import org.onechurch.events.application.EventMessageReview;
import org.onechurch.events.application.EventReadiness;
import org.onechurch.events.application.EventRegistrationOperations;
import org.onechurch.events.application.EventReports;
import org.onechurch.events.application.command.approveeventnotification.ApproveEventNotificationHandler;
import org.onechurch.events.application.command.checkineventperson.CheckInEventPersonHandler;
import org.onechurch.events.application.command.closeeventoccurrence.CloseEventOccurrenceHandler;
import org.onechurch.events.application.command.correcteventattendance.CorrectEventAttendanceHandler;
import org.onechurch.events.application.command.registereventwalkin.RegisterEventWalkInHandler;
import org.onechurch.events.application.command.reopeneventcloseout.ReopenEventCloseoutHandler;
import org.onechurch.events.application.contract.AttendanceReader;
import org.onechurch.events.application.contract.CloseoutReader;
import org.onechurch.events.application.contract.EventJournal;
import org.onechurch.events.application.contract.EventJournalReader;
import org.onechurch.events.application.contract.EventNotificationReader;
import org.onechurch.events.application.contract.EventNotificationWriter;
import org.onechurch.events.application.contract.EventReader;
import org.onechurch.events.application.contract.EventReportReader;
import org.onechurch.events.application.contract.OccurrenceReader;
import org.onechurch.events.application.contract.RegistrationReader;
import org.onechurch.events.application.contract.VenueReader;
import org.onechurch.events.application.query.exporteventattendancereport.ExportEventAttendanceReportHandler;
import org.onechurch.events.application.query.geteventattendancehistory.GetEventAttendanceHistoryHandler;
import org.onechurch.events.application.query.geteventattendancereport.GetEventAttendanceReportHandler;
import org.onechurch.events.application.query.geteventcloseouthistory.GetEventCloseoutHistoryHandler;
import org.onechurch.events.application.query.geteventreadiness.GetEventReadinessHandler;
import org.onechurch.events.application.query.listeventattendance.ListEventAttendanceHandler;
import org.onechurch.events.application.query.listeventdeliveries.ListEventDeliveriesHandler;
import org.onechurch.events.application.query.listeventmessagecontacts.ListEventMessageContactsHandler;
import org.onechurch.events.application.query.revieweventnotification.ReviewEventNotificationHandler;
import org.onechurch.events.domain.repository.AttendanceRepository;
import org.onechurch.events.domain.repository.CloseoutRepository;
import org.onechurch.events.domain.repository.OccurrenceRepository;
import org.onechurch.identityaccess.application.contract.AccountNames;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.people.application.contract.PersonEmailDirectory;
import org.onechurch.shared.application.UnitOfWork;
import org.onechurch.volunteering.application.contract.ActivityStaffing;
import org.springframework.beans.factory.annotation.Qualifier;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import java.time.Clock;
@Configuration
public class EventDayConfiguration {

    @Bean
    EventReadiness eventReadiness(OccurrenceReader occurrences, EventReader events, VenueReader venues,
        RegistrationReader registrations, AttendanceReader attendance, CloseoutReader closeouts,
        @Qualifier("communicationDeliveryStore") DeliveryReader deliveries, ActivityStaffing staffing, Clock clock,
        AccountNames accounts) {
        return new EventReadiness(occurrences, events, venues, registrations, attendance, closeouts, deliveries,
            staffing, clock, accounts);
    }
    @Bean
    EventCloseouts eventCloseouts(OccurrenceRepository occurrences, CloseoutReader reader,
        CloseoutRepository repository, EventReadiness readiness, EventJournal journal, UnitOfWork tx, Clock clock) {
        return new EventCloseouts(occurrences, reader, repository, readiness, journal, tx, clock);
    }
    @Bean
    CloseEventOccurrenceHandler closeEventOccurrenceHandler(PermissionChecker auth, EventCloseouts service) {
        return new CloseEventOccurrenceHandler(auth, service);
    }
    @Bean
    ReopenEventCloseoutHandler reopenEventCloseoutHandler(PermissionChecker auth, EventCloseouts service) {
        return new ReopenEventCloseoutHandler(auth, service);
    }
    @Bean
    GetEventReadinessHandler getEventReadinessHandler(PermissionChecker auth, EventReadiness service) {
        return new GetEventReadinessHandler(auth, service);
    }
    @Bean
    GetEventCloseoutHistoryHandler getEventCloseoutHistoryHandler(PermissionChecker auth, EventReadiness service) {
        return new GetEventCloseoutHistoryHandler(auth, service);
    }

    @Bean
    AttendanceViews attendanceViews(AttendanceReader attendance, OccurrenceReader occurrences, Clock clock) {
        return new AttendanceViews(attendance, occurrences, clock);
    }
    @Bean
    EventReports eventReports(EventReportReader reader, Clock clock) {
        return new EventReports(reader, clock);
    }
    @Bean
    EventAttendanceOperations eventAttendanceOperations(AttendanceReader reader, AttendanceRepository repository,
        RegistrationReader registrations, OccurrenceRepository occurrences, EventReader events, PersonDirectory people,
        EventJournal journal, EventJournalReader requests, EventRegistrationOperations bookings, UnitOfWork tx,
        Clock clock) {
        return new EventAttendanceOperations(reader, repository, registrations, occurrences, events, people, journal,
            requests, bookings, tx, clock);
    }
    @Bean
    EventMessageReview eventMessageReview(RegistrationReader registrations, OccurrenceReader occurrences,
        EventReader events, PersonDirectory people, PersonEmailDirectory emails, EmailTemplates templates) {
        return new EventMessageReview(registrations, occurrences, events, people, emails, templates);
    }
    @Bean
    EventMessageApproval eventMessageApproval(EventMessageReview review, EventNotificationReader reader,
        EventNotificationWriter writer, RegistrationReader registrations, OccurrenceRepository occurrences,
        PersonDirectory people, EmailDeliveries emails, EventJournal journal, UnitOfWork tx, Clock clock) {
        return new EventMessageApproval(review, reader, writer, registrations, occurrences, people, emails, journal, tx,
            clock);
    }
    @Bean
    CheckInEventPersonHandler checkInEventPersonHandler(PermissionChecker auth, EventAttendanceOperations attendance) {
        return new CheckInEventPersonHandler(auth, attendance);
    }
    @Bean
    RegisterEventWalkInHandler registerEventWalkInHandler(PermissionChecker auth,
        EventAttendanceOperations attendance) {
        return new RegisterEventWalkInHandler(auth, attendance);
    }
    @Bean
    CorrectEventAttendanceHandler correctEventAttendanceHandler(PermissionChecker auth,
        EventAttendanceOperations attendance) {
        return new CorrectEventAttendanceHandler(auth, attendance);
    }
    @Bean
    ApproveEventNotificationHandler approveEventNotificationHandler(PermissionChecker auth,
        EventMessageApproval approval) {
        return new ApproveEventNotificationHandler(auth, approval);
    }
    @Bean
    ListEventAttendanceHandler listEventAttendanceHandler(PermissionChecker auth, AttendanceViews views) {
        return new ListEventAttendanceHandler(auth, views);
    }
    @Bean
    GetEventAttendanceHistoryHandler getEventAttendanceHistoryHandler(PermissionChecker auth, AttendanceViews views) {
        return new GetEventAttendanceHistoryHandler(auth, views);
    }
    @Bean
    GetEventAttendanceReportHandler getEventAttendanceReportHandler(PermissionChecker auth, EventReports reports) {
        return new GetEventAttendanceReportHandler(auth, reports);
    }
    @Bean
    ExportEventAttendanceReportHandler exportEventAttendanceReportHandler(PermissionChecker auth,
        EventReports reports) {
        return new ExportEventAttendanceReportHandler(auth, reports);
    }
    @Bean
    ListEventMessageContactsHandler listEventMessageContactsHandler(PermissionChecker auth, EventMessageReview review) {
        return new ListEventMessageContactsHandler(auth, review);
    }
    @Bean
    ReviewEventNotificationHandler reviewEventNotificationHandler(PermissionChecker auth, EventMessageReview review,
        Clock clock) {
        return new ReviewEventNotificationHandler(auth, review, clock);
    }
    @Bean
    ListEventDeliveriesHandler listEventDeliveriesHandler(PermissionChecker auth,
        @Qualifier("communicationDeliveryStore") DeliveryReader deliveries) {
        return new ListEventDeliveriesHandler(auth, deliveries);
    }
}
