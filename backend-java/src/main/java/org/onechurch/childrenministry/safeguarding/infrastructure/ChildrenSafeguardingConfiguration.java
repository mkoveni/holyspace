package org.onechurch.childrenministry.safeguarding.infrastructure;
import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.participation.domain.repository.ParticipationRepository;
import org.onechurch.childrenministry.safeguarding.application.ChildReadiness;
import org.onechurch.childrenministry.safeguarding.application.SafeguardingFollowUp;
import org.onechurch.childrenministry.safeguarding.application.command.accesschildsupport.AccessChildSupportHandler;
import org.onechurch.childrenministry.safeguarding.application.command.authorizepickup.AuthorizePickupHandler;
import org.onechurch.childrenministry.safeguarding.application.command.publishconsentpolicy.PublishConsentPolicyHandler;
import org.onechurch.childrenministry.safeguarding.application.command.purgeexpiredsupport.PurgeExpiredSupportHandler;
import org.onechurch.childrenministry.safeguarding.application.command.recordconsent.RecordConsentHandler;
import org.onechurch.childrenministry.safeguarding.application.command.revokeconsent.RevokeConsentHandler;
import org.onechurch.childrenministry.safeguarding.application.command.revokeguardian.RevokeGuardianHandler;
import org.onechurch.childrenministry.safeguarding.application.command.revokepickupauthorization.RevokePickupAuthorizationHandler;
import org.onechurch.childrenministry.safeguarding.application.command.savechildsupport.SaveChildSupportHandler;
import org.onechurch.childrenministry.safeguarding.application.command.verifyguardian.VerifyGuardianHandler;
import org.onechurch.childrenministry.safeguarding.application.contract.ReadinessReader;
import org.onechurch.childrenministry.safeguarding.application.contract.SafeguardingFollowUpReader;
import org.onechurch.childrenministry.safeguarding.application.contract.SafeguardingReader;
import org.onechurch.childrenministry.safeguarding.application.contract.SupportReader;
import org.onechurch.childrenministry.safeguarding.application.query.getchildreadiness.GetChildReadinessHandler;
import org.onechurch.childrenministry.safeguarding.application.query.getconsentpolicy.GetConsentPolicyHandler;
import org.onechurch.childrenministry.safeguarding.application.query.getsafeguarding.GetSafeguardingHandler;
import org.onechurch.childrenministry.safeguarding.application.query.getsafeguardingfollowup.GetSafeguardingFollowUpHandler;
import org.onechurch.childrenministry.safeguarding.domain.repository.SafeguardingRepository;
import org.onechurch.childrenministry.safeguarding.domain.repository.SupportRepository;
import org.onechurch.childrenministry.sessions.application.ParticipantAccess;
import org.onechurch.childrenministry.sessions.application.SessionOperations;
import org.onechurch.childrenministry.sessions.application.StaffScope;
import org.onechurch.childrenministry.sessions.application.contract.AttendanceReader;
import org.onechurch.childrenministry.sessions.application.contract.SessionReader;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.application.UnitOfWork;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import java.time.Clock;
import java.time.ZoneId;
@Configuration
public class ChildrenSafeguardingConfiguration {
    @Bean
    PublishConsentPolicyHandler publishConsentPolicyHandler(PermissionChecker authorization, ChildrenAudit audit,
        UnitOfWork transactions, Clock clock, ParticipationRepository participationRepository) {
        return new PublishConsentPolicyHandler(authorization, audit, transactions, clock, participationRepository);
    }
    @Bean
    VerifyGuardianHandler verifyGuardianHandler(PermissionChecker authorization, ChildrenAudit audit,
        UnitOfWork transactions, Clock clock, ParticipantAccess participants,
        SafeguardingRepository safeguardingRepository, ZoneId zone) {
        return new VerifyGuardianHandler(authorization, audit, transactions, clock, participants,
            safeguardingRepository, zone);
    }
    @Bean
    RecordConsentHandler recordConsentHandler(PermissionChecker authorization, ChildrenAudit audit,
        UnitOfWork transactions, Clock clock, ParticipantAccess participants, SafeguardingReader safeguarding,
        SafeguardingRepository safeguardingRepository, ParticipationReader participation) {
        return new RecordConsentHandler(authorization, audit, transactions, clock, participants, safeguarding,
            safeguardingRepository, participation);
    }
    @Bean
    RevokeGuardianHandler revokeGuardianHandler(PermissionChecker authorization, ChildrenAudit audit,
        UnitOfWork transactions, Clock clock, ParticipantAccess participants, SafeguardingReader safeguarding,
        SafeguardingRepository safeguardingRepository) {
        return new RevokeGuardianHandler(authorization, audit, transactions, clock, participants, safeguarding,
            safeguardingRepository);
    }
    @Bean
    RevokeConsentHandler revokeConsentHandler(PermissionChecker authorization, ChildrenAudit audit,
        UnitOfWork transactions, Clock clock, ParticipantAccess participants, SafeguardingReader safeguarding,
        SafeguardingRepository safeguardingRepository) {
        return new RevokeConsentHandler(authorization, audit, transactions, clock, participants, safeguarding,
            safeguardingRepository);
    }
    @Bean
    RevokePickupAuthorizationHandler revokePickupAuthorizationHandler(PermissionChecker authorization,
        ChildrenAudit audit, UnitOfWork transactions, Clock clock, ParticipantAccess participants,
        SafeguardingReader safeguarding, SafeguardingRepository safeguardingRepository) {
        return new RevokePickupAuthorizationHandler(authorization, audit, transactions, clock, participants,
            safeguarding, safeguardingRepository);
    }
    @Bean
    AuthorizePickupHandler authorizePickupHandler(PermissionChecker authorization, ChildrenAudit audit,
        UnitOfWork transactions, Clock clock, ParticipantAccess participants, ParticipationReader participation,
        SafeguardingReader safeguarding, SafeguardingRepository safeguardingRepository, ZoneId zone) {
        return new AuthorizePickupHandler(authorization, audit, transactions, clock, participants, participation,
            safeguarding, safeguardingRepository, zone);
    }
    @Bean
    SaveChildSupportHandler saveChildSupportHandler(PermissionChecker authorization, ChildrenAudit audit,
        UnitOfWork transactions, Clock clock, ParticipantAccess participants, SupportReader support,
        SupportRepository supportRepository, ZoneId zone) {
        return new SaveChildSupportHandler(authorization, audit, transactions, clock, participants, support,
            supportRepository, zone);
    }
    @Bean
    AccessChildSupportHandler accessChildSupportHandler(PermissionChecker authorization, ChildrenAudit audit,
        UnitOfWork transactions, Clock clock, StaffScope scope, SupportReader support, AttendanceReader attendance,
        ParticipationReader participation, SessionReader session, SessionOperations operations, ZoneId zone) {
        return new AccessChildSupportHandler(authorization, audit, transactions, clock, scope, support, attendance,
            participation, session, operations, zone);
    }
    @Bean
    PurgeExpiredSupportHandler purgeExpiredSupportHandler(PermissionChecker authorization, ChildrenAudit audit,
        UnitOfWork transactions, Clock clock, SupportReader support, SupportRepository supportRepository,
        ParticipantAccess participants, ZoneId zone) {
        return new PurgeExpiredSupportHandler(authorization, audit, transactions, clock, support, supportRepository,
            participants, zone);
    }
    @Bean
    GetSafeguardingHandler getSafeguardingHandler(PermissionChecker authorization, SafeguardingReader safeguarding) {
        return new GetSafeguardingHandler(authorization, safeguarding);
    }
    @Bean
    GetConsentPolicyHandler getConsentPolicyHandler(PermissionChecker authorization,
        ParticipationReader participation) {
        return new GetConsentPolicyHandler(authorization, participation);
    }
    @Bean
    ChildReadiness childReadiness(ReadinessReader reader, PersonDirectory people, ParticipationReader participation,
        Clock clock, ZoneId zone) {
        return new ChildReadiness(reader, people, participation, clock, zone);
    }
    @Bean
    GetChildReadinessHandler getChildReadinessHandler(PermissionChecker authorization,
        ParticipationReader participation, PersonDirectory people, ChildReadiness readiness) {
        return new GetChildReadinessHandler(authorization, participation, people, readiness);
    }
    @Bean
    SafeguardingFollowUp safeguardingFollowUp(SafeguardingFollowUpReader reader, PersonDirectory people, Clock clock,
        ZoneId zone) {
        return new SafeguardingFollowUp(reader, people, clock, zone);
    }
    @Bean
    GetSafeguardingFollowUpHandler getSafeguardingFollowUpHandler(PermissionChecker authorization,
        SafeguardingFollowUp followUp) {
        return new GetSafeguardingFollowUpHandler(authorization, followUp);
    }
}
