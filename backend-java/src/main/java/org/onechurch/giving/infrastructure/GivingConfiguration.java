package org.onechurch.giving.infrastructure;
import org.onechurch.giving.application.GivingAccess;
import org.onechurch.giving.application.command.createfund.CreateFundHandler;
import org.onechurch.giving.application.command.recordgift.RecordGiftHandler;
import org.onechurch.giving.application.command.reversegift.ReverseGiftHandler;
import org.onechurch.giving.application.command.updatefund.UpdateFundHandler;
import org.onechurch.giving.application.contract.GivingReader;
import org.onechurch.giving.application.query.getgift.GetGiftHandler;
import org.onechurch.giving.application.query.getgivingtotals.GetGivingTotalsHandler;
import org.onechurch.giving.application.query.listgifts.ListGiftsHandler;
import org.onechurch.giving.application.query.listgivingfunds.ListGivingFundsHandler;
import org.onechurch.giving.domain.repository.GivingRepository;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.application.UnitOfWork;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import java.time.Clock;
import java.time.ZoneId;
@Configuration
public class GivingConfiguration {
    @Bean
    org.onechurch.giving.application.contract.OnlineGivingLedger onlineGivingLedger(GivingRepository giving,
        org.onechurch.giving.domain.repository.OnlineGivingRepository online,
        org.onechurch.giving.domain.repository.PledgeRepository pledges, PersonDirectory people, UnitOfWork tx,
        Clock clock, ZoneId zone) {
        return new org.onechurch.giving.application.OnlineGivingService(giving, online, pledges, people, tx,
            clock.withZone(zone));
    }
    @Bean
    GivingAccess givingAccess(PermissionChecker auth) {
        return new GivingAccess(auth);
    }
    @Bean
    CreateFundHandler createFundHandler(GivingAccess access, GivingRepository repository, UnitOfWork tx, Clock clock,
        ZoneId zone) {
        return new CreateFundHandler(access, repository, tx, clock.withZone(zone));
    }
    @Bean
    UpdateFundHandler updateFundHandler(GivingAccess access, GivingRepository repository, UnitOfWork tx, Clock clock,
        ZoneId zone) {
        return new UpdateFundHandler(access, repository, tx, clock.withZone(zone));
    }
    @Bean
    RecordGiftHandler recordGiftHandler(GivingAccess access, GivingRepository repository, UnitOfWork tx, Clock clock,
        ZoneId zone, PersonDirectory people) {
        return new RecordGiftHandler(access, repository, tx, clock.withZone(zone), people);
    }
    @Bean
    ReverseGiftHandler reverseGiftHandler(GivingAccess access, GivingRepository repository, UnitOfWork tx, Clock clock,
        ZoneId zone) {
        return new ReverseGiftHandler(access, repository, tx, clock.withZone(zone));
    }
    @Bean
    ListGivingFundsHandler listGivingFundsHandler(GivingAccess access, GivingReader reader) {
        return new ListGivingFundsHandler(access, reader);
    }
    @Bean
    ListGiftsHandler listGiftsHandler(GivingAccess access, GivingReader reader) {
        return new ListGiftsHandler(access, reader);
    }
    @Bean
    GetGiftHandler getGiftHandler(GivingAccess access, GivingReader reader) {
        return new GetGiftHandler(access, reader);
    }
    @Bean
    GetGivingTotalsHandler getGivingTotalsHandler(GivingAccess access, GivingReader reader) {
        return new GetGivingTotalsHandler(access, reader);
    }
}
