package org.onechurch.people.presentation;

import org.onechurch.people.application.command.createrelationship.CreateRelationshipCommand;
import org.onechurch.people.application.command.createrelationship.CreateRelationshipHandler;
import org.onechurch.people.application.command.endrelationship.EndRelationshipCommand;
import org.onechurch.people.application.command.endrelationship.EndRelationshipHandler;
import org.onechurch.people.presentation.api.PeopleApi.Created;
import org.onechurch.people.presentation.api.RelationshipApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.UUID;

@RestController
public class RelationshipController implements RelationshipApi {

    private final CreateRelationshipHandler create;
    private final EndRelationshipHandler end;

    public RelationshipController(CreateRelationshipHandler create, EndRelationshipHandler end) {
        this.create = create;
        this.end = end;
    }

    @Override
    @PreAuthorize("hasAuthority('people.manage')")

    public Created create(OAuth2AuthenticatedPrincipal actor, Create request) {
        return new Created(create.handle(
            new CreateRelationshipCommand(actor(actor), request.sourceId(), request.targetId(), request.type())));
    }

    @Override
    @PreAuthorize("hasAuthority('people.manage')")

    public void end(OAuth2AuthenticatedPrincipal actor, UUID id, Revision request) {
        end.handle(new EndRelationshipCommand(actor(actor), id, request.expectedVersion()));
    }

    private static UUID actor(OAuth2AuthenticatedPrincipal actor) {
        return UUID.fromString(actor.getName());
    }
}
