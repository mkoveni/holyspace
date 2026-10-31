package org.onechurch.childrenministry.messaging.infrastructure;
import org.onechurch.childrenministry.messaging.application.DeliveryCommands;
import org.onechurch.childrenministry.messaging.application.DeliveryPolicy;
import org.onechurch.childrenministry.messaging.application.GuardianCommunicationPolicy;
import org.onechurch.childrenministry.messaging.application.command.cancelemaildelivery.CancelEmailDeliveryHandler;
import org.onechurch.childrenministry.messaging.application.command.createguardiancommunication.CreateGuardianCommunicationHandler;
import org.onechurch.childrenministry.messaging.application.command.queueguardianemail.QueueGuardianEmailHandler;
import org.onechurch.childrenministry.messaging.application.command.recorddeliveryreceipt.RecordDeliveryReceiptHandler;
import org.onechurch.childrenministry.messaging.application.command.recordguardiancontact.RecordGuardianContactHandler;
import org.onechurch.childrenministry.messaging.application.command.retryemaildelivery.RetryEmailDeliveryHandler;
import org.onechurch.childrenministry.messaging.application.command.revokeguardianemail.RevokeGuardianEmailHandler;
import org.onechurch.childrenministry.messaging.application.command.verifyguardianemail.VerifyGuardianEmailHandler;
import org.onechurch.childrenministry.messaging.application.contract.DeliveryReader;
import org.onechurch.childrenministry.messaging.application.contract.DeliveryWriter;
import org.onechurch.childrenministry.messaging.application.contract.GuardianCommunicationReader;
import org.onechurch.childrenministry.messaging.application.query.getemaildelivery.GetEmailDeliveryHandler;
import org.onechurch.childrenministry.messaging.application.query.getemailreview.GetEmailReviewHandler;
import org.onechurch.childrenministry.messaging.application.query.getguardiancommunication.GetGuardianCommunicationHandler;
import org.onechurch.childrenministry.messaging.application.query.listemaildeliveries.ListEmailDeliveriesHandler;
import org.onechurch.childrenministry.messaging.application.query.listguardiancommunications.ListGuardianCommunicationsHandler;
import org.onechurch.childrenministry.messaging.domain.repository.GuardianCommunicationRepository;
import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.safeguarding.application.contract.SafeguardingReader;
import org.onechurch.childrenministry.safeguarding.domain.repository.SafeguardingRepository;
import org.onechurch.childrenministry.sessions.application.contract.SessionReader;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.communications.application.contract.EmailDeliveries;
import org.onechurch.communications.application.contract.EmailTemplates;
import org.onechurch.communications.application.contract.EmailTransport;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.people.application.contract.PersonEmailDirectory;
import org.onechurch.shared.application.UnitOfWork;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import java.time.Clock;
import java.time.ZoneId;
@Configuration
public class ChildrenMessagingConfiguration {
    @Bean
    GuardianCommunicationPolicy guardianCommunicationPolicy(SafeguardingReader safeguarding,
        ParticipationReader participation, SessionReader sessions, PersonDirectory people, ZoneId zone) {
        return new GuardianCommunicationPolicy(safeguarding, participation, sessions, people, zone);
    }
    @Bean
    CreateGuardianCommunicationHandler createGuardianCommunicationHandler(PermissionChecker auth,
        GuardianCommunicationPolicy policy, GuardianCommunicationReader reader,
        GuardianCommunicationRepository repository, SafeguardingRepository safeguarding, PersonDirectory people,
        UnitOfWork tx, ChildrenAudit audit, Clock clock, ZoneId zone) {
        return new CreateGuardianCommunicationHandler(auth, policy, reader, repository, safeguarding, people, tx, audit,
            clock, zone);
    }
    @Bean
    RecordGuardianContactHandler recordGuardianContactHandler(PermissionChecker auth,
        GuardianCommunicationPolicy policy, GuardianCommunicationRepository repository,
        SafeguardingRepository safeguarding, UnitOfWork tx, ChildrenAudit audit, Clock clock,
        DeliveryReader deliveries) {
        return new RecordGuardianContactHandler(auth, policy, repository, safeguarding, tx, audit, clock, deliveries);
    }
    @Bean
    ListGuardianCommunicationsHandler listGuardianCommunicationsHandler(PermissionChecker auth,
        GuardianCommunicationReader reader, PersonDirectory people, Clock clock, ZoneId zone,
        DeliveryReader deliveries) {
        return new ListGuardianCommunicationsHandler(auth, reader, people, clock, zone, deliveries);
    }
    @Bean
    GetGuardianCommunicationHandler getGuardianCommunicationHandler(PermissionChecker auth,
        GuardianCommunicationReader reader, GuardianCommunicationPolicy policy, Clock clock) {
        return new GetGuardianCommunicationHandler(auth, reader, policy, clock);
    }
    @Bean
    DeliveryPolicy deliveryPolicy(GuardianCommunicationPolicy policy, GuardianCommunicationReader communications,
        DeliveryReader reader, PersonEmailDirectory emails, EmailTemplates templates) {
        return new DeliveryPolicy(policy, communications, reader, emails, templates);
    }
    @Bean
    DeliveryCommands deliveryCommands(DeliveryReader reader, DeliveryWriter writer, DeliveryPolicy policy,
        GuardianCommunicationRepository communications, PersonDirectory people, PersonEmailDirectory emails,
        UnitOfWork tx, ChildrenAudit audit, Clock clock, EmailTransport transport, EmailDeliveries deliveries) {
        return new DeliveryCommands(reader, writer, policy, communications, people, emails, tx, audit, clock, transport,
            deliveries);
    }
    @Bean
    VerifyGuardianEmailHandler verifyGuardianEmailHandler(PermissionChecker auth, DeliveryCommands commands) {
        return new VerifyGuardianEmailHandler(auth, commands);
    }
    @Bean
    RevokeGuardianEmailHandler revokeGuardianEmailHandler(PermissionChecker auth, DeliveryCommands commands) {
        return new RevokeGuardianEmailHandler(auth, commands);
    }
    @Bean
    QueueGuardianEmailHandler queueGuardianEmailHandler(PermissionChecker auth, DeliveryCommands commands) {
        return new QueueGuardianEmailHandler(auth, commands);
    }
    @Bean
    CancelEmailDeliveryHandler cancelEmailDeliveryHandler(PermissionChecker auth, DeliveryCommands commands) {
        return new CancelEmailDeliveryHandler(auth, commands);
    }
    @Bean
    RetryEmailDeliveryHandler retryEmailDeliveryHandler(PermissionChecker auth, DeliveryCommands commands) {
        return new RetryEmailDeliveryHandler(auth, commands);
    }
    @Bean
    RecordDeliveryReceiptHandler recordDeliveryReceiptHandler(PermissionChecker auth, DeliveryCommands commands) {
        return new RecordDeliveryReceiptHandler(auth, commands);
    }
    @Bean
    GetEmailReviewHandler getEmailReviewHandler(PermissionChecker auth, DeliveryPolicy policy, DeliveryReader reader,
        PersonEmailDirectory emails, EmailTransport transport, Clock clock) {
        return new GetEmailReviewHandler(auth, policy, reader, emails, transport, clock);
    }
    @Bean
    ListEmailDeliveriesHandler listEmailDeliveriesHandler(PermissionChecker auth, DeliveryReader reader) {
        return new ListEmailDeliveriesHandler(auth, reader);
    }
    @Bean
    GetEmailDeliveryHandler getEmailDeliveryHandler(PermissionChecker auth, DeliveryReader reader) {
        return new GetEmailDeliveryHandler(auth, reader);
    }
}
