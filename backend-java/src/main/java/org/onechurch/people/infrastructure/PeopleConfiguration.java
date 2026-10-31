package org.onechurch.people.infrastructure;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.command.addaddress.AddAddressHandler;
import org.onechurch.people.application.command.addcontact.AddContactHandler;
import org.onechurch.people.application.command.addhouseholdmember.AddHouseholdMemberHandler;
import org.onechurch.people.application.command.createhousehold.CreateHouseholdHandler;
import org.onechurch.people.application.command.createrelationship.CreateRelationshipHandler;
import org.onechurch.people.application.command.createtitle.CreateTitleHandler;
import org.onechurch.people.application.command.endaddress.EndAddressHandler;
import org.onechurch.people.application.command.endcontact.EndContactHandler;
import org.onechurch.people.application.command.endhouseholdmembership.EndHouseholdMembershipHandler;
import org.onechurch.people.application.command.endrelationship.EndRelationshipHandler;
import org.onechurch.people.application.command.registerperson.RegisterPersonHandler;
import org.onechurch.people.application.command.sethouseholdcontact.SetHouseholdContactHandler;
import org.onechurch.people.application.command.setpersonarchived.SetPersonArchivedHandler;
import org.onechurch.people.application.command.updatehousehold.UpdateHouseholdHandler;
import org.onechurch.people.application.command.updateperson.UpdatePersonHandler;
import org.onechurch.people.application.contract.HouseholdReader;
import org.onechurch.people.application.contract.PeopleAuditWriter;
import org.onechurch.people.application.contract.PersonReader;
import org.onechurch.people.application.contract.RelationshipReader;
import org.onechurch.people.application.contract.TitleReader;
import org.onechurch.people.application.contract.TitleWriter;
import org.onechurch.people.application.query.gethousehold.GetHouseholdHandler;
import org.onechurch.people.application.query.getpeoplemetadata.GetPeopleMetadataHandler;
import org.onechurch.people.application.query.getperson.GetPersonHandler;
import org.onechurch.people.application.query.getpersonhouseholds.GetPersonHouseholdsHandler;
import org.onechurch.people.application.query.getpersonrelationships.GetPersonRelationshipsHandler;
import org.onechurch.people.application.query.searchhouseholds.SearchHouseholdsHandler;
import org.onechurch.people.application.query.searchpeople.SearchPeopleHandler;
import org.onechurch.people.domain.repository.HouseholdRepository;
import org.onechurch.people.domain.repository.PersonRepository;
import org.onechurch.people.domain.repository.RelationshipRepository;
import org.onechurch.shared.application.UnitOfWork;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import java.time.Clock;
import java.time.ZoneId;

@Configuration
public class PeopleConfiguration {
    @Bean
    ZoneId churchZone(@Value("${onechurch.people.time-zone:Africa/Johannesburg}") String zone) {
        return ZoneId.of(zone);
    }
    @Bean
    RegisterPersonHandler registerPersonHandler(PermissionChecker authorization, PeopleAuditWriter audit,
        UnitOfWork transactions, Clock clock, PersonRepository people, TitleReader titles, ZoneId zone) {
        return new RegisterPersonHandler(authorization, audit, transactions, clock, people, titles, zone);
    }
    @Bean
    UpdatePersonHandler updatePersonHandler(PermissionChecker authorization, PeopleAuditWriter audit,
        UnitOfWork transactions, Clock clock, PersonRepository people, TitleReader titles, ZoneId zone) {
        return new UpdatePersonHandler(authorization, audit, transactions, clock, people, titles, zone);
    }
    @Bean
    SetPersonArchivedHandler setPersonArchivedHandler(PermissionChecker authorization, PeopleAuditWriter audit,
        UnitOfWork transactions, Clock clock, PersonRepository people) {
        return new SetPersonArchivedHandler(authorization, audit, transactions, clock, people);
    }
    @Bean
    CreateTitleHandler createTitleHandler(PermissionChecker authorization, PeopleAuditWriter audit,
        UnitOfWork transactions, Clock clock, TitleWriter titleWriter) {
        return new CreateTitleHandler(authorization, audit, transactions, clock, titleWriter);
    }
    @Bean
    AddContactHandler addContactHandler(PermissionChecker authorization, PeopleAuditWriter audit,
        UnitOfWork transactions, Clock clock, PersonRepository people) {
        return new AddContactHandler(authorization, audit, transactions, clock, people);
    }
    @Bean
    EndContactHandler endContactHandler(PermissionChecker authorization, PeopleAuditWriter audit,
        UnitOfWork transactions, Clock clock, PersonRepository people) {
        return new EndContactHandler(authorization, audit, transactions, clock, people);
    }
    @Bean
    AddAddressHandler addAddressHandler(PermissionChecker authorization, PeopleAuditWriter audit,
        UnitOfWork transactions, Clock clock, PersonRepository people) {
        return new AddAddressHandler(authorization, audit, transactions, clock, people);
    }
    @Bean
    EndAddressHandler endAddressHandler(PermissionChecker authorization, PeopleAuditWriter audit,
        UnitOfWork transactions, Clock clock, PersonRepository people) {
        return new EndAddressHandler(authorization, audit, transactions, clock, people);
    }
    @Bean
    CreateHouseholdHandler createHouseholdHandler(PermissionChecker authorization, PeopleAuditWriter audit,
        UnitOfWork transactions, Clock clock, HouseholdRepository households) {
        return new CreateHouseholdHandler(authorization, audit, transactions, clock, households);
    }
    @Bean
    UpdateHouseholdHandler updateHouseholdHandler(PermissionChecker authorization, PeopleAuditWriter audit,
        UnitOfWork transactions, Clock clock, HouseholdRepository households) {
        return new UpdateHouseholdHandler(authorization, audit, transactions, clock, households);
    }
    @Bean
    AddHouseholdMemberHandler addHouseholdMemberHandler(PermissionChecker authorization, PeopleAuditWriter audit,
        UnitOfWork transactions, Clock clock, HouseholdRepository households, PersonRepository people) {
        return new AddHouseholdMemberHandler(authorization, audit, transactions, clock, households, people);
    }
    @Bean
    EndHouseholdMembershipHandler endHouseholdMembershipHandler(PermissionChecker authorization,
        PeopleAuditWriter audit, UnitOfWork transactions, Clock clock, HouseholdRepository households) {
        return new EndHouseholdMembershipHandler(authorization, audit, transactions, clock, households);
    }
    @Bean
    SetHouseholdContactHandler setHouseholdContactHandler(PermissionChecker authorization, PeopleAuditWriter audit,
        UnitOfWork transactions, Clock clock, HouseholdRepository households) {
        return new SetHouseholdContactHandler(authorization, audit, transactions, clock, households);
    }
    @Bean
    CreateRelationshipHandler createRelationshipHandler(PermissionChecker authorization, PeopleAuditWriter audit,
        UnitOfWork transactions, Clock clock, RelationshipRepository relationships, PersonRepository people) {
        return new CreateRelationshipHandler(authorization, audit, transactions, clock, relationships, people);
    }
    @Bean
    EndRelationshipHandler endRelationshipHandler(PermissionChecker authorization, PeopleAuditWriter audit,
        UnitOfWork transactions, Clock clock, RelationshipRepository relationships) {
        return new EndRelationshipHandler(authorization, audit, transactions, clock, relationships);
    }
    @Bean
    GetPersonHandler getPersonHandler(PermissionChecker authorization, PersonReader persons, Clock clock, ZoneId zone) {
        return new GetPersonHandler(authorization, persons, clock, zone);
    }
    @Bean
    SearchPeopleHandler searchPeopleHandler(PermissionChecker authorization, PersonReader persons, Clock clock,
        ZoneId zone) {
        return new SearchPeopleHandler(authorization, persons, clock, zone);
    }
    @Bean
    GetHouseholdHandler getHouseholdHandler(PermissionChecker authorization, HouseholdReader homes) {
        return new GetHouseholdHandler(authorization, homes);
    }
    @Bean
    SearchHouseholdsHandler searchHouseholdsHandler(PermissionChecker authorization, HouseholdReader homes) {
        return new SearchHouseholdsHandler(authorization, homes);
    }
    @Bean
    GetPersonHouseholdsHandler getPersonHouseholdsHandler(PermissionChecker authorization, PersonReader persons,
        HouseholdReader homes) {
        return new GetPersonHouseholdsHandler(authorization, persons, homes);
    }
    @Bean
    GetPersonRelationshipsHandler getPersonRelationshipsHandler(PermissionChecker authorization, PersonReader persons,
        RelationshipReader links) {
        return new GetPersonRelationshipsHandler(authorization, persons, links);
    }
    @Bean
    GetPeopleMetadataHandler getPeopleMetadataHandler(PermissionChecker authorization, TitleReader titles,
        ZoneId zone) {
        return new GetPeopleMetadataHandler(authorization, titles, zone);
    }
}
