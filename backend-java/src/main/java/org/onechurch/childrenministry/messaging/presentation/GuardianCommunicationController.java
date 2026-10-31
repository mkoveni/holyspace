package org.onechurch.childrenministry.messaging.presentation;
import org.onechurch.childrenministry.messaging.application.command.createguardiancommunication.CreateGuardianCommunicationCommand;
import org.onechurch.childrenministry.messaging.application.command.createguardiancommunication.CreateGuardianCommunicationHandler;
import org.onechurch.childrenministry.messaging.application.command.recordguardiancontact.RecordGuardianContactCommand;
import org.onechurch.childrenministry.messaging.application.command.recordguardiancontact.RecordGuardianContactHandler;
import org.onechurch.childrenministry.messaging.application.query.getguardiancommunication.GetGuardianCommunicationHandler;
import org.onechurch.childrenministry.messaging.application.query.getguardiancommunication.GetGuardianCommunicationQuery;
import org.onechurch.childrenministry.messaging.application.query.listguardiancommunications.ListGuardianCommunicationsHandler;
import org.onechurch.childrenministry.messaging.application.query.listguardiancommunications.ListGuardianCommunicationsQuery;
import org.onechurch.childrenministry.messaging.domain.GuardianCommunication;
import org.onechurch.childrenministry.messaging.presentation.api.GuardianCommunicationApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.UUID;
@RestController
@PreAuthorize("hasAuthority('children.safeguarding')")
public class GuardianCommunicationController implements GuardianCommunicationApi {
    private final CreateGuardianCommunicationHandler prepare;
    private final RecordGuardianContactHandler contact;
    private final ListGuardianCommunicationsHandler list;
    private final GetGuardianCommunicationHandler get;
    public GuardianCommunicationController(CreateGuardianCommunicationHandler prepare,
        RecordGuardianContactHandler contact, ListGuardianCommunicationsHandler list,
        GetGuardianCommunicationHandler get) {
        this.prepare = prepare;
        this.contact = contact;
        this.list = list;
        this.get = get;
    }
    @Override
    public Created prepare(Prepare b, OAuth2AuthenticatedPrincipal p) {
        return new Created(prepare.handle(new CreateGuardianCommunicationCommand(UUID.fromString(p.getName()),
            b.requestId(), b.childId(), b.authorityId(), b.programmeId(), b.sessionId(), b.type(), b.dueOn())));
    }
    @Override
    public ListGuardianCommunicationsHandler.View list(UUID childId, String status, int limit, int offset) {
        return list.handle(new ListGuardianCommunicationsQuery(childId, status, limit, offset));
    }
    @Override
    public GuardianCommunication get(UUID id) {
        return get.handle(new GetGuardianCommunicationQuery(id));
    }
    @Override
    public void contact(UUID id, Contact b, OAuth2AuthenticatedPrincipal p) {
        contact.handle(new RecordGuardianContactCommand(UUID.fromString(p.getName()), id, b.expectedVersion(),
            b.status(), b.reference()));
    }
}
