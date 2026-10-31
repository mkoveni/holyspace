package org.onechurch.volunteering.infrastructure;

import org.onechurch.communications.application.contract.DeliveryReader;
import org.onechurch.communications.application.contract.EmailDeliveries;
import org.onechurch.communications.application.contract.EmailTemplates;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.people.application.contract.PersonEmailDirectory;
import org.onechurch.shared.application.UnitOfWork;
import org.onechurch.volunteering.application.ReminderReview;
import org.onechurch.volunteering.application.VolunteerProfiles;
import org.onechurch.volunteering.application.command.approvevolunteerreminder.ApproveVolunteerReminderHandler;
import org.onechurch.volunteering.application.contract.ReminderReader;
import org.onechurch.volunteering.application.contract.ReminderWriter;
import org.onechurch.volunteering.application.contract.RosterReader;
import org.onechurch.volunteering.application.contract.ServingAudit;
import org.onechurch.volunteering.application.contract.TeamReader;
import org.onechurch.volunteering.application.contract.VolunteerReader;
import org.onechurch.volunteering.application.query.listremindercontacts.ListReminderContactsHandler;
import org.onechurch.volunteering.application.query.listvolunteerdeliveries.ListVolunteerDeliveriesHandler;
import org.onechurch.volunteering.application.query.reviewvolunteerreminder.ReviewVolunteerReminderHandler;
import org.onechurch.volunteering.domain.repository.RosterRepository;
import org.springframework.beans.factory.annotation.Qualifier;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import java.time.Clock;

@Configuration
public class VolunteerRemindersConfiguration {
    @Bean
    ReminderReview reminderReview(RosterReader roster, VolunteerReader volunteers, TeamReader teams,
        PersonDirectory people, PersonEmailDirectory emails, EmailTemplates templates) {
        return new ReminderReview(roster, volunteers, teams, people, emails, templates);
    }
    @Bean
    ApproveVolunteerReminderHandler approveVolunteerReminder(PermissionChecker auth, UnitOfWork tx,
        VolunteerProfiles profiles, RosterReader roster, RosterRepository repository, ReminderReview review,
        ReminderReader reader, ReminderWriter writer, EmailDeliveries emails, ServingAudit audit, Clock clock) {
        return new ApproveVolunteerReminderHandler(auth, tx, profiles, roster, repository, review, reader, writer,
            emails, audit, clock);
    }
    @Bean
    ReviewVolunteerReminderHandler reviewVolunteerReminder(PermissionChecker auth, ReminderReview review, Clock clock) {
        return new ReviewVolunteerReminderHandler(auth, review, clock);
    }
    @Bean
    ListReminderContactsHandler listReminderContacts(PermissionChecker auth, ReminderReview review) {
        return new ListReminderContactsHandler(auth, review);
    }
    @Bean
    ListVolunteerDeliveriesHandler listVolunteerDeliveries(PermissionChecker auth,
        @Qualifier("communicationDeliveryStore") DeliveryReader deliveries) {
        return new ListVolunteerDeliveriesHandler(auth, deliveries);
    }
}
