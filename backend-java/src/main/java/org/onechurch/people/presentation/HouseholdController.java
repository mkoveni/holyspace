package org.onechurch.people.presentation;

import org.onechurch.people.application.HouseholdView;
import org.onechurch.people.application.command.addhouseholdmember.AddHouseholdMemberCommand;
import org.onechurch.people.application.command.addhouseholdmember.AddHouseholdMemberHandler;
import org.onechurch.people.application.command.createhousehold.CreateHouseholdCommand;
import org.onechurch.people.application.command.createhousehold.CreateHouseholdHandler;
import org.onechurch.people.application.command.endhouseholdmembership.EndHouseholdMembershipCommand;
import org.onechurch.people.application.command.endhouseholdmembership.EndHouseholdMembershipHandler;
import org.onechurch.people.application.command.sethouseholdcontact.SetHouseholdContactCommand;
import org.onechurch.people.application.command.sethouseholdcontact.SetHouseholdContactHandler;
import org.onechurch.people.application.command.updatehousehold.UpdateHouseholdCommand;
import org.onechurch.people.application.command.updatehousehold.UpdateHouseholdHandler;
import org.onechurch.people.application.query.gethousehold.GetHouseholdHandler;
import org.onechurch.people.application.query.gethousehold.GetHouseholdQuery;
import org.onechurch.people.application.query.searchhouseholds.SearchHouseholdsHandler;
import org.onechurch.people.application.query.searchhouseholds.SearchHouseholdsQuery;
import org.onechurch.people.domain.Household;
import org.onechurch.people.presentation.api.HouseholdApi;
import org.onechurch.people.presentation.api.PeopleApi.Created;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.List;
import java.util.UUID;

@RestController
public class HouseholdController implements HouseholdApi {

    private final SearchHouseholdsHandler search;
    private final GetHouseholdHandler get;
    private final CreateHouseholdHandler create;
    private final UpdateHouseholdHandler update;
    private final AddHouseholdMemberHandler member;
    private final EndHouseholdMembershipHandler end;
    private final SetHouseholdContactHandler contact;

    public HouseholdController(SearchHouseholdsHandler search, GetHouseholdHandler get, CreateHouseholdHandler create,
        UpdateHouseholdHandler update, AddHouseholdMemberHandler member, EndHouseholdMembershipHandler end,
        SetHouseholdContactHandler contact) {
        this.search = search;
        this.get = get;
        this.create = create;
        this.update = update;
        this.member = member;
        this.end = end;
        this.contact = contact;
    }

    @Override
    @PreAuthorize("hasAnyAuthority('people.read', 'people.manage')")

    public List<Household> search(String search, int limit, int offset) {
        return this.search.handle(new SearchHouseholdsQuery(search, limit, offset));
    }

    @Override
    @PreAuthorize("hasAnyAuthority('people.read', 'people.manage')")

    public HouseholdView get(UUID id) {
        return get.handle(new GetHouseholdQuery(id));
    }

    @Override
    @PreAuthorize("hasAuthority('people.manage')")

    public Created create(OAuth2AuthenticatedPrincipal actor, Create request) {
        return new Created(create.handle(new CreateHouseholdCommand(actor(actor), request.name())));
    }

    @Override
    @PreAuthorize("hasAuthority('people.manage')")

    public void update(OAuth2AuthenticatedPrincipal actor, UUID id, Rename request) {
        update.handle(new UpdateHouseholdCommand(actor(actor), id, request.expectedVersion(), request.name()));
    }

    @Override
    @PreAuthorize("hasAuthority('people.manage')")

    public Created member(OAuth2AuthenticatedPrincipal actor, UUID id, AddMember request) {
        return new Created(member.handle(new AddHouseholdMemberCommand(actor(actor), id, request.expectedVersion(),
            request.personId(), request.primary())));
    }

    @Override
    @PreAuthorize("hasAuthority('people.manage')")

    public void end(OAuth2AuthenticatedPrincipal actor, UUID id, UUID membershipId, Revision request) {
        end.handle(new EndHouseholdMembershipCommand(actor(actor), id, request.expectedVersion(), membershipId));
    }

    @Override
    @PreAuthorize("hasAuthority('people.manage')")

    public void contact(OAuth2AuthenticatedPrincipal actor, UUID id, Contact request) {
        contact.handle(
            new SetHouseholdContactCommand(actor(actor), id, request.expectedVersion(), request.membershipId()));
    }

    private static UUID actor(OAuth2AuthenticatedPrincipal actor) {
        return UUID.fromString(actor.getName());
    }
}
