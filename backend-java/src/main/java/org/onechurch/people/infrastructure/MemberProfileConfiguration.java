package org.onechurch.people.infrastructure;
import org.onechurch.identityaccess.application.contract.MemberIdentity;
import org.onechurch.identityaccess.application.contract.MemberIdentityGuard;
import org.onechurch.people.application.command.removemycontact.RemoveMyContactHandler;
import org.onechurch.people.application.command.savemycontact.SaveMyContactHandler;
import org.onechurch.people.application.command.updatemyprofile.UpdateMyProfileHandler;
import org.onechurch.people.application.contract.PeopleAuditWriter;
import org.onechurch.people.application.contract.PersonReader;
import org.onechurch.people.application.contract.TitleReader;
import org.onechurch.people.application.query.getmyprofile.GetMyProfileHandler;
import org.onechurch.people.domain.repository.PersonRepository;
import org.onechurch.shared.application.UnitOfWork;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import java.time.Clock;
import java.time.ZoneId;
@Configuration
public class MemberProfileConfiguration {
    @Bean
    GetMyProfileHandler getMyProfile(MemberIdentity identity, PersonReader people, TitleReader titles) {
        return new GetMyProfileHandler(identity, people, titles);
    }
    @Bean
    UpdateMyProfileHandler updateMyProfile(MemberIdentity identity, MemberIdentityGuard guard, PersonRepository people,
        TitleReader titles, PeopleAuditWriter audit, UnitOfWork tx, Clock clock, ZoneId zone) {
        return new UpdateMyProfileHandler(identity, guard, people, titles, audit, tx, clock, zone);
    }
    @Bean
    SaveMyContactHandler saveMyContact(MemberIdentity identity, MemberIdentityGuard guard, PersonRepository people,
        PersonReader reader, PeopleAuditWriter audit, UnitOfWork tx, Clock clock) {
        return new SaveMyContactHandler(identity, guard, people, reader, audit, tx, clock);
    }
    @Bean
    RemoveMyContactHandler removeMyContact(MemberIdentity identity, MemberIdentityGuard guard, PersonRepository people,
        PersonReader reader, PeopleAuditWriter audit, UnitOfWork tx, Clock clock) {
        return new RemoveMyContactHandler(identity, guard, people, reader, audit, tx, clock);
    }
}
