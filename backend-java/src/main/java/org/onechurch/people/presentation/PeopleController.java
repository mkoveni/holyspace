package org.onechurch.people.presentation;

import org.onechurch.people.application.PeopleMetadata;
import org.onechurch.people.application.PersonSummary;
import org.onechurch.people.application.PersonView;
import org.onechurch.people.application.RelationshipView;
import org.onechurch.people.application.command.addaddress.AddAddressCommand;
import org.onechurch.people.application.command.addaddress.AddAddressHandler;
import org.onechurch.people.application.command.addcontact.AddContactCommand;
import org.onechurch.people.application.command.addcontact.AddContactHandler;
import org.onechurch.people.application.command.createtitle.CreateTitleCommand;
import org.onechurch.people.application.command.createtitle.CreateTitleHandler;
import org.onechurch.people.application.command.endaddress.EndAddressCommand;
import org.onechurch.people.application.command.endaddress.EndAddressHandler;
import org.onechurch.people.application.command.endcontact.EndContactCommand;
import org.onechurch.people.application.command.endcontact.EndContactHandler;
import org.onechurch.people.application.command.registerperson.RegisterPersonCommand;
import org.onechurch.people.application.command.registerperson.RegisterPersonHandler;
import org.onechurch.people.application.command.setpersonarchived.SetPersonArchivedCommand;
import org.onechurch.people.application.command.setpersonarchived.SetPersonArchivedHandler;
import org.onechurch.people.application.command.updateperson.UpdatePersonCommand;
import org.onechurch.people.application.command.updateperson.UpdatePersonHandler;
import org.onechurch.people.application.query.getpeoplemetadata.GetPeopleMetadataHandler;
import org.onechurch.people.application.query.getpeoplemetadata.GetPeopleMetadataQuery;
import org.onechurch.people.application.query.getperson.GetPersonHandler;
import org.onechurch.people.application.query.getperson.GetPersonQuery;
import org.onechurch.people.application.query.getpersonhouseholds.GetPersonHouseholdsHandler;
import org.onechurch.people.application.query.getpersonhouseholds.GetPersonHouseholdsQuery;
import org.onechurch.people.application.query.getpersonrelationships.GetPersonRelationshipsHandler;
import org.onechurch.people.application.query.getpersonrelationships.GetPersonRelationshipsQuery;
import org.onechurch.people.application.query.searchpeople.SearchPeopleHandler;
import org.onechurch.people.application.query.searchpeople.SearchPeopleQuery;
import org.onechurch.people.domain.AgeCategory;
import org.onechurch.people.domain.HouseholdMembership;
import org.onechurch.people.domain.MembershipType;
import org.onechurch.people.presentation.api.PeopleApi;
import org.onechurch.people.presentation.api.PeopleApi.Created;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.time.LocalDate;
import java.util.List;
import java.util.UUID;

@RestController
public class PeopleController implements PeopleApi {

    private final SearchPeopleHandler search;
    private final GetPersonHandler get;
    private final GetPeopleMetadataHandler metadata;
    private final RegisterPersonHandler create;
    private final UpdatePersonHandler update;
    private final SetPersonArchivedHandler archive;
    private final CreateTitleHandler titles;
    private final AddContactHandler contacts;
    private final EndContactHandler endContact;
    private final AddAddressHandler addresses;
    private final EndAddressHandler endAddress;
    private final GetPersonHouseholdsHandler households;
    private final GetPersonRelationshipsHandler relationships;

    public PeopleController(SearchPeopleHandler search, GetPersonHandler get, GetPeopleMetadataHandler metadata,
        RegisterPersonHandler create, UpdatePersonHandler update, SetPersonArchivedHandler archive,
        CreateTitleHandler titles, AddContactHandler contacts, EndContactHandler endContact,
        AddAddressHandler addresses, EndAddressHandler endAddress, GetPersonHouseholdsHandler households,
        GetPersonRelationshipsHandler relationships) {
        this.search = search;
        this.get = get;
        this.metadata = metadata;
        this.create = create;
        this.update = update;
        this.archive = archive;
        this.titles = titles;
        this.contacts = contacts;
        this.endContact = endContact;
        this.addresses = addresses;
        this.endAddress = endAddress;
        this.households = households;
        this.relationships = relationships;
    }

    @Override
    @PreAuthorize("hasAnyAuthority('people.read', 'people.manage')")

    public List<PersonSummary> search(String search, boolean includeArchived, MembershipType membershipType,
        AgeCategory ageCategory, LocalDate asOf, int limit, int offset) {
        return this.search
            .handle(new SearchPeopleQuery(search, includeArchived, membershipType, ageCategory, asOf, limit, offset));
    }

    @Override
    @PreAuthorize("hasAnyAuthority('people.read', 'people.manage')")

    public PersonView get(UUID id, LocalDate asOf) {
        return get.handle(new GetPersonQuery(id, asOf));
    }

    @Override
    @PreAuthorize("hasAnyAuthority('people.read', 'people.manage')")

    public PeopleMetadata metadata() {
        return metadata.handle(new GetPeopleMetadataQuery());
    }

    @Override
    @PreAuthorize("hasAuthority('people.manage')")

    public Created create(OAuth2AuthenticatedPrincipal actor, Register request) {
        return new Created(create
            .handle(new RegisterPersonCommand(actor(actor), request.person().details(), request.membershipType())));
    }

    @Override
    @PreAuthorize("hasAuthority('people.manage')")

    public void update(OAuth2AuthenticatedPrincipal actor, UUID id, Profile request) {
        update.handle(new UpdatePersonCommand(actor(actor), id, request.expectedVersion(), request.person().details(),
            request.membershipType()));
    }

    @Override
    @PreAuthorize("hasAuthority('people.manage')")

    public void archive(OAuth2AuthenticatedPrincipal actor, UUID id, Archive request) {
        archive.handle(new SetPersonArchivedCommand(actor(actor), id, request.expectedVersion(), request.archived()));
    }

    @Override
    @PreAuthorize("hasAuthority('people.manage')")

    public void title(OAuth2AuthenticatedPrincipal actor, Title request) {
        titles.handle(new CreateTitleCommand(actor(actor), request.code(), request.label()));
    }

    @Override
    @PreAuthorize("hasAuthority('people.manage')")

    public Created contact(OAuth2AuthenticatedPrincipal actor, UUID id, Contact request) {
        return new Created(contacts.handle(new AddContactCommand(actor(actor), id, request.expectedVersion(),
            request.kind(), request.value(), request.primary())));
    }

    @Override
    @PreAuthorize("hasAuthority('people.manage')")

    public void endContact(OAuth2AuthenticatedPrincipal actor, UUID id, UUID contactId, Revision request) {
        endContact.handle(new EndContactCommand(actor(actor), id, request.expectedVersion(), contactId));
    }

    @Override
    @PreAuthorize("hasAuthority('people.manage')")

    public Created address(OAuth2AuthenticatedPrincipal actor, UUID id, Address request) {
        return new Created(
            addresses.handle(new AddAddressCommand(actor(actor), id, request.expectedVersion(), request.line1(),
                request.line2(), request.city(), request.region(), request.postalCode(), request.countryCode())));
    }

    @Override
    @PreAuthorize("hasAuthority('people.manage')")

    public void endAddress(OAuth2AuthenticatedPrincipal actor, UUID id, UUID addressId, Revision request) {
        endAddress.handle(new EndAddressCommand(actor(actor), id, request.expectedVersion(), addressId));
    }

    @Override
    @PreAuthorize("hasAnyAuthority('people.read', 'people.manage')")

    public List<HouseholdMembership> households(UUID id) {
        return households.handle(new GetPersonHouseholdsQuery(id));
    }

    @Override
    @PreAuthorize("hasAnyAuthority('people.read', 'people.manage')")

    public List<RelationshipView> relationships(UUID id) {
        return relationships.handle(new GetPersonRelationshipsQuery(id));
    }

    private static UUID actor(OAuth2AuthenticatedPrincipal actor) {
        return UUID.fromString(actor.getName());
    }
}
