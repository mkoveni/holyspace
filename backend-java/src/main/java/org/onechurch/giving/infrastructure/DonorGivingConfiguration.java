package org.onechurch.giving.infrastructure;
import org.onechurch.giving.application.VerifiedDonorIdentity;
import org.onechurch.giving.application.command.setonlinefund.SetOnlineFundHandler;
import org.onechurch.giving.application.command.verifydonorlink.VerifyDonorLinkHandler;
import org.onechurch.giving.application.contract.DonorLinkReader;
import org.onechurch.giving.application.contract.OnlineFundDirectory;
import org.onechurch.giving.application.query.listdonoraccess.ListDonorAccessHandler;
import org.onechurch.giving.domain.repository.DonorLinkRepository;
import org.onechurch.giving.domain.repository.GivingRepository;
import org.onechurch.identityaccess.application.contract.AccountDirectory;
import org.onechurch.identityaccess.application.contract.AccountIdentityGuard;
import org.onechurch.identityaccess.application.contract.AccountNames;
import org.onechurch.identityaccess.application.contract.AuthenticatedAccount;
import org.onechurch.identityaccess.application.contract.MemberIdentity;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.application.UnitOfWork;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import java.time.Clock;
@Configuration
public class DonorGivingConfiguration {
    @Bean
    VerifiedDonorIdentity donorIdentity(AuthenticatedAccount account, DonorLinkReader reader, PersonDirectory people,
        DonorLinkRepository repository) {
        return new VerifiedDonorIdentity(account, reader, people, repository);
    }
    @Bean
    VerifyDonorLinkHandler verifyDonorLink(PermissionChecker auth, AccountDirectory accounts, PersonDirectory people,
        DonorLinkRepository links, UnitOfWork tx, Clock clock, AccountIdentityGuard identityGuard,
        MemberIdentity members) {
        return new VerifyDonorLinkHandler(auth, accounts, people, links, tx, clock, identityGuard, members);
    }
    @Bean
    SetOnlineFundHandler setOnlineFund(PermissionChecker auth, GivingRepository funds, DonorLinkRepository links,
        UnitOfWork tx, Clock clock) {
        return new SetOnlineFundHandler(auth, funds, links, tx, clock);
    }
    @Bean
    ListDonorAccessHandler listDonorAccess(PermissionChecker auth, DonorLinkReader reader, OnlineFundDirectory funds,
        PersonDirectory people, AccountNames names) {
        return new ListDonorAccessHandler(auth, reader, funds, people, names);
    }
}
