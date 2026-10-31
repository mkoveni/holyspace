package org.onechurch.giving.infrastructure;
import org.onechurch.giving.application.GivingAccess;
import org.onechurch.giving.application.GivingReports;
import org.onechurch.giving.application.command.assignpledgegift.AssignPledgeGiftHandler;
import org.onechurch.giving.application.command.cancelpledge.CancelPledgeHandler;
import org.onechurch.giving.application.command.cancelpledgeassignment.CancelPledgeAssignmentHandler;
import org.onechurch.giving.application.command.createpledge.CreatePledgeHandler;
import org.onechurch.giving.application.command.createreconciliation.CreateReconciliationHandler;
import org.onechurch.giving.application.command.issueacknowledgement.IssueAcknowledgementHandler;
import org.onechurch.giving.application.command.reviewreconciliation.ReviewReconciliationHandler;
import org.onechurch.giving.application.contract.AcknowledgementReader;
import org.onechurch.giving.application.contract.GivingLedgerReader;
import org.onechurch.giving.application.contract.GivingReader;
import org.onechurch.giving.application.contract.PledgeReader;
import org.onechurch.giving.application.contract.ReconciliationReader;
import org.onechurch.giving.application.query.exportgivingledger.ExportGivingLedgerHandler;
import org.onechurch.giving.application.query.getacknowledgement.GetAcknowledgementHandler;
import org.onechurch.giving.application.query.getdonorstatement.GetDonorStatementHandler;
import org.onechurch.giving.application.query.getgiftacknowledgement.GetGiftAcknowledgementHandler;
import org.onechurch.giving.application.query.getpledge.GetPledgeHandler;
import org.onechurch.giving.application.query.getreconciliation.GetReconciliationHandler;
import org.onechurch.giving.application.query.listpledgegiftcandidates.ListPledgeGiftCandidatesHandler;
import org.onechurch.giving.application.query.listpledges.ListPledgesHandler;
import org.onechurch.giving.application.query.listreconciliations.ListReconciliationsHandler;
import org.onechurch.giving.domain.repository.AcknowledgementRepository;
import org.onechurch.giving.domain.repository.GivingRepository;
import org.onechurch.giving.domain.repository.PledgeRepository;
import org.onechurch.giving.domain.repository.ReconciliationRepository;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.application.UnitOfWork;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import java.time.Clock;
@Configuration
public class GivingReportingConfiguration {
    @Bean
    GivingReports givingReports(GivingLedgerReader reader, PersonDirectory people, Clock clock) {
        return new GivingReports(reader, people, clock);
    }
    @Bean
    CreatePledgeHandler createPledgeHandler(GivingAccess access, GivingRepository giving, UnitOfWork tx, Clock clock,
        java.time.ZoneId zone, PledgeRepository pledges, PersonDirectory people) {
        return new CreatePledgeHandler(access, giving, tx, clock.withZone(zone), pledges, people);
    }
    @Bean
    CancelPledgeHandler cancelPledgeHandler(GivingAccess access, GivingRepository giving, UnitOfWork tx, Clock clock,
        java.time.ZoneId zone, PledgeRepository pledges) {
        return new CancelPledgeHandler(access, giving, tx, clock.withZone(zone), pledges);
    }
    @Bean
    AssignPledgeGiftHandler assignPledgeGiftHandler(GivingAccess access, GivingRepository giving, UnitOfWork tx,
        Clock clock, java.time.ZoneId zone, PledgeRepository pledges) {
        return new AssignPledgeGiftHandler(access, giving, tx, clock.withZone(zone), pledges);
    }
    @Bean
    CancelPledgeAssignmentHandler cancelPledgeAssignmentHandler(GivingAccess access, GivingRepository giving,
        UnitOfWork tx, Clock clock, java.time.ZoneId zone, PledgeRepository pledges) {
        return new CancelPledgeAssignmentHandler(access, giving, tx, clock.withZone(zone), pledges);
    }
    @Bean
    IssueAcknowledgementHandler issueAcknowledgementHandler(GivingAccess access, GivingRepository giving, UnitOfWork tx,
        Clock clock, java.time.ZoneId zone, AcknowledgementRepository acknowledgements, GivingReader reader) {
        return new IssueAcknowledgementHandler(access, giving, tx, clock.withZone(zone), acknowledgements, reader);
    }
    @Bean
    CreateReconciliationHandler createReconciliationHandler(GivingAccess access, GivingRepository giving, UnitOfWork tx,
        Clock clock, java.time.ZoneId zone, ReconciliationRepository batches) {
        return new CreateReconciliationHandler(access, giving, tx, clock.withZone(zone), batches);
    }
    @Bean
    ReviewReconciliationHandler reviewReconciliationHandler(GivingAccess access, GivingRepository giving, UnitOfWork tx,
        Clock clock, java.time.ZoneId zone, ReconciliationRepository batches) {
        return new ReviewReconciliationHandler(access, giving, tx, clock.withZone(zone), batches);
    }
    @Bean
    ListPledgesHandler listPledgesHandler(GivingAccess access, PledgeReader reader) {
        return new ListPledgesHandler(access, reader);
    }
    @Bean
    GetPledgeHandler getPledgeHandler(GivingAccess access, PledgeReader reader) {
        return new GetPledgeHandler(access, reader);
    }
    @Bean
    ListPledgeGiftCandidatesHandler listPledgeGiftCandidatesHandler(GivingAccess access, PledgeReader reader) {
        return new ListPledgeGiftCandidatesHandler(access, reader);
    }
    @Bean
    GetGiftAcknowledgementHandler getGiftAcknowledgementHandler(GivingAccess access, AcknowledgementReader reader) {
        return new GetGiftAcknowledgementHandler(access, reader);
    }
    @Bean
    GetAcknowledgementHandler getAcknowledgementHandler(GivingAccess access, AcknowledgementReader reader) {
        return new GetAcknowledgementHandler(access, reader);
    }
    @Bean
    ListReconciliationsHandler listReconciliationsHandler(GivingAccess access, ReconciliationReader reader) {
        return new ListReconciliationsHandler(access, reader);
    }
    @Bean
    GetReconciliationHandler getReconciliationHandler(GivingAccess access, ReconciliationReader reader) {
        return new GetReconciliationHandler(access, reader);
    }
    @Bean
    GetDonorStatementHandler getDonorStatementHandler(GivingAccess access, GivingReports reports) {
        return new GetDonorStatementHandler(access, reports);
    }
    @Bean
    ExportGivingLedgerHandler exportGivingLedgerHandler(GivingAccess access, GivingReports reports) {
        return new ExportGivingLedgerHandler(access, reports);
    }
}
