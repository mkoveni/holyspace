package org.onechurch.volunteering.infrastructure;
import org.onechurch.identityaccess.application.contract.AuthenticatedAccount;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;
import org.onechurch.volunteering.application.MemberVolunteerActions;
import org.onechurch.volunteering.application.MemberVolunteerViews;
import org.onechurch.volunteering.application.RosterPlanning;
import org.onechurch.volunteering.application.ServingViews;
import org.onechurch.volunteering.application.VolunteerProfiles;
import org.onechurch.volunteering.application.VolunteerReadiness;
import org.onechurch.volunteering.application.command.respondmyvolunteerinvitation.RespondMyVolunteerInvitationHandler;
import org.onechurch.volunteering.application.command.updatemyvolunteeravailability.UpdateMyVolunteerAvailabilityHandler;
import org.onechurch.volunteering.application.contract.ActivitySource;
import org.onechurch.volunteering.application.contract.MemberDutyReader;
import org.onechurch.volunteering.application.contract.RosterReader;
import org.onechurch.volunteering.application.contract.TeamReader;
import org.onechurch.volunteering.application.contract.VolunteerReader;
import org.onechurch.volunteering.application.query.getmyvolunteering.GetMyVolunteeringHandler;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import java.time.Clock;
import java.util.List;
@Configuration
public class MemberVolunteeringConfiguration {
    @Bean
    MemberVolunteerViews memberVolunteerViews(VolunteerReader volunteers, MemberDutyReader duties, RosterReader roster,
        TeamReader teams, ServingViews views, VolunteerReadiness readiness, List<ActivitySource> sources, Clock clock) {
        return new MemberVolunteerViews(volunteers, duties, roster, teams, views, readiness, sources, clock);
    }
    @Bean
    MemberVolunteerActions memberVolunteerActions(AuthenticatedAccount identity, PermissionChecker auth,
        VolunteerReader volunteers, VolunteerProfiles profiles, RosterPlanning planning, UnitOfWork tx,
        RosterReader roster) {
        return new MemberVolunteerActions(identity, auth, volunteers, profiles, planning, tx, roster);
    }
    @Bean
    GetMyVolunteeringHandler getMyVolunteering(AuthenticatedAccount identity, PermissionChecker auth,
        MemberVolunteerViews views) {
        return new GetMyVolunteeringHandler(identity, auth, views);
    }
    @Bean
    RespondMyVolunteerInvitationHandler respondMyVolunteerInvitation(MemberVolunteerActions actions) {
        return new RespondMyVolunteerInvitationHandler(actions);
    }
    @Bean
    UpdateMyVolunteerAvailabilityHandler updateMyVolunteerAvailability(MemberVolunteerActions actions) {
        return new UpdateMyVolunteerAvailabilityHandler(actions);
    }
}
