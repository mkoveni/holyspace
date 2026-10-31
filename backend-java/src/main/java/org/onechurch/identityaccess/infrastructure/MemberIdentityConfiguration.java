package org.onechurch.identityaccess.infrastructure;
import org.onechurch.identityaccess.application.VerifiedMemberIdentity;
import org.onechurch.identityaccess.application.authorization.Authorization;
import org.onechurch.identityaccess.application.command.revokemysession.RevokeMySessionHandler;
import org.onechurch.identityaccess.application.command.verifymemberlink.VerifyMemberLinkHandler;
import org.onechurch.identityaccess.application.contract.AccountDirectory;
import org.onechurch.identityaccess.application.contract.AccountIdentityGuard;
import org.onechurch.identityaccess.application.contract.AccountNames;
import org.onechurch.identityaccess.application.contract.CurrentActor;
import org.onechurch.identityaccess.application.contract.DeviceSessionReader;
import org.onechurch.identityaccess.application.contract.IdentityAuditWriter;
import org.onechurch.identityaccess.application.contract.MemberLinkReader;
import org.onechurch.identityaccess.application.contract.PersonBindingPolicy;
import org.onechurch.identityaccess.application.contract.SessionReader;
import org.onechurch.identityaccess.application.contract.SessionWriter;
import org.onechurch.identityaccess.application.query.listmemberaccess.ListMemberAccessHandler;
import org.onechurch.identityaccess.application.query.listmysessions.ListMySessionsHandler;
import org.onechurch.identityaccess.domain.repository.MemberLinkRepository;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.application.UnitOfWork;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import java.time.Clock;
import java.util.List;
@Configuration
public class MemberIdentityConfiguration {
    @Bean
    VerifiedMemberIdentity memberIdentity(Authorization auth, MemberLinkReader reader, MemberLinkRepository links,
        PersonDirectory people) {
        return new VerifiedMemberIdentity(auth, reader, links, people);
    }
    @Bean
    ListMemberAccessHandler listMemberAccess(Authorization auth, MemberLinkReader reader, AccountNames names,
        PersonDirectory people) {
        return new ListMemberAccessHandler(auth, reader, names, people);
    }
    @Bean
    VerifyMemberLinkHandler verifyMemberLink(Authorization auth, AccountIdentityGuard guard, AccountDirectory accounts,
        MemberLinkRepository links, PersonDirectory people, List<PersonBindingPolicy> policies, UnitOfWork tx,
        Clock clock) {
        return new VerifyMemberLinkHandler(auth, guard, accounts, links, people, policies, tx, clock);
    }
    @Bean
    ListMySessionsHandler listMySessions(Authorization auth, CurrentActor current, DeviceSessionReader reader,
        Clock clock) {
        return new ListMySessionsHandler(auth, current, reader, clock);
    }
    @Bean
    RevokeMySessionHandler revokeMySession(Authorization auth, AccountIdentityGuard guard, SessionReader reader,
        SessionWriter writer, IdentityAuditWriter audit, UnitOfWork tx, Clock clock) {
        return new RevokeMySessionHandler(auth, guard, reader, writer, audit, tx, clock);
    }
}
