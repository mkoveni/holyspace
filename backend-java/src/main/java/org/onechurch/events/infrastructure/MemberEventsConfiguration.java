package org.onechurch.events.infrastructure;
import org.onechurch.communications.application.contract.MemberNotifications;
import org.onechurch.events.application.EventAttendanceOperations;
import org.onechurch.events.application.EventRegistrationOperations;
import org.onechurch.events.application.MemberEventActions;
import org.onechurch.events.application.command.cancelmyevent.CancelMyEventHandler;
import org.onechurch.events.application.command.configurememberevent.ConfigureMemberEventHandler;
import org.onechurch.events.application.command.issuemyeventpass.IssueMyEventPassHandler;
import org.onechurch.events.application.command.registermyevent.RegisterMyEventHandler;
import org.onechurch.events.application.command.scanmemberattendance.ScanMemberAttendanceHandler;
import org.onechurch.events.application.contract.EventJournal;
import org.onechurch.events.application.contract.MemberEventReader;
import org.onechurch.events.application.contract.MemberEventWriter;
import org.onechurch.events.application.contract.OccurrenceReader;
import org.onechurch.events.application.contract.RegistrationReader;
import org.onechurch.events.application.query.getmyevent.GetMyEventHandler;
import org.onechurch.events.application.query.listmyevents.ListMyEventsHandler;
import org.onechurch.events.domain.repository.AdmissionPassRepository;
import org.onechurch.events.domain.repository.EventRepository;
import org.onechurch.events.domain.repository.OccurrenceRepository;
import org.onechurch.identityaccess.application.contract.AccountDirectory;
import org.onechurch.identityaccess.application.contract.CurrentActor;
import org.onechurch.identityaccess.application.contract.MemberAdmissionGuard;
import org.onechurch.identityaccess.application.contract.MemberIdentity;
import org.onechurch.identityaccess.application.contract.MemberIdentityGuard;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.identityaccess.application.contract.SessionReader;
import org.onechurch.identityaccess.application.contract.TokenCodec;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.application.UnitOfWork;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import java.time.Clock;
@Configuration
public class MemberEventsConfiguration {
    @Bean
    org.onechurch.events.application.query.getmemberoffer.GetMemberOfferHandler getMemberOffer(PermissionChecker auth,
        MemberEventReader reader, org.onechurch.events.application.contract.EventReader events) {
        return new org.onechurch.events.application.query.getmemberoffer.GetMemberOfferHandler(auth, reader, events);
    }
    @Bean
    MemberEventActions memberEventActions(MemberIdentity identity, MemberIdentityGuard guard,
        MemberAdmissionGuard admissionGuard, MemberEventReader reader, MemberEventWriter writer,
        OccurrenceReader occurrences, OccurrenceRepository occurrenceRepository, EventRepository events,
        RegistrationReader registrations, EventRegistrationOperations bookings, EventAttendanceOperations attendance,
        AdmissionPassRepository passes, PersonDirectory people, CurrentActor current, PermissionChecker auth,
        TokenCodec tokens, SessionReader sessions, AccountDirectory accounts, EventJournal journal, UnitOfWork tx,
        Clock clock, MemberNotifications notifications) {
        return new MemberEventActions(identity, guard, admissionGuard, reader, writer, occurrences,
            occurrenceRepository, events, registrations, bookings, attendance, passes, people, current, auth, tokens,
            sessions, accounts, journal, tx, clock, notifications);
    }
    @Bean
    ListMyEventsHandler listMyEvents(MemberIdentity identity, MemberEventReader reader, Clock clock) {
        return new ListMyEventsHandler(identity, reader, clock);
    }
    @Bean
    GetMyEventHandler getMyEvent(MemberIdentity identity, MemberEventReader reader, Clock clock) {
        return new GetMyEventHandler(identity, reader, clock);
    }
    @Bean
    RegisterMyEventHandler registerMyEvent(MemberEventActions actions, MemberIdentity auth) {
        return new RegisterMyEventHandler(actions, auth);
    }
    @Bean
    CancelMyEventHandler cancelMyEvent(MemberEventActions actions, MemberIdentity auth) {
        return new CancelMyEventHandler(actions, auth);
    }
    @Bean
    IssueMyEventPassHandler issueMyEventPass(MemberEventActions actions, MemberIdentity auth) {
        return new IssueMyEventPassHandler(actions, auth);
    }
    @Bean
    ScanMemberAttendanceHandler scanMemberAttendance(MemberEventActions actions, PermissionChecker auth) {
        return new ScanMemberAttendanceHandler(actions, auth);
    }
    @Bean
    ConfigureMemberEventHandler configureMemberEvent(MemberEventActions actions, PermissionChecker auth) {
        return new ConfigureMemberEventHandler(actions, auth);
    }
}
