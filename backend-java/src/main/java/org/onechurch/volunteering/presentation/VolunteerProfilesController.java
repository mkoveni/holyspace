package org.onechurch.volunteering.presentation;

import org.onechurch.volunteering.application.ServingViews;
import org.onechurch.volunteering.application.command.changevolunteerstatus.ChangeVolunteerStatusCommand;
import org.onechurch.volunteering.application.command.changevolunteerstatus.ChangeVolunteerStatusHandler;
import org.onechurch.volunteering.application.command.createcredentialtype.CreateCredentialTypeCommand;
import org.onechurch.volunteering.application.command.createcredentialtype.CreateCredentialTypeHandler;
import org.onechurch.volunteering.application.command.recordvolunteercredential.RecordVolunteerCredentialCommand;
import org.onechurch.volunteering.application.command.recordvolunteercredential.RecordVolunteerCredentialHandler;
import org.onechurch.volunteering.application.command.registervolunteer.RegisterVolunteerCommand;
import org.onechurch.volunteering.application.command.registervolunteer.RegisterVolunteerHandler;
import org.onechurch.volunteering.application.command.revokevolunteercredential.RevokeVolunteerCredentialCommand;
import org.onechurch.volunteering.application.command.revokevolunteercredential.RevokeVolunteerCredentialHandler;
import org.onechurch.volunteering.application.command.setvolunteeravailability.SetVolunteerAvailabilityCommand;
import org.onechurch.volunteering.application.command.setvolunteeravailability.SetVolunteerAvailabilityHandler;
import org.onechurch.volunteering.application.query.getvolunteer.GetVolunteerHandler;
import org.onechurch.volunteering.application.query.getvolunteer.GetVolunteerQuery;
import org.onechurch.volunteering.application.query.listcredentialtypes.ListCredentialTypesHandler;
import org.onechurch.volunteering.application.query.listcredentialtypes.ListCredentialTypesQuery;
import org.onechurch.volunteering.application.query.listvolunteers.ListVolunteersHandler;
import org.onechurch.volunteering.application.query.listvolunteers.ListVolunteersQuery;
import org.onechurch.volunteering.domain.CredentialType;
import org.onechurch.volunteering.presentation.api.VolunteerProfilesApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.List;
import java.util.UUID;

@RestController
public class VolunteerProfilesController implements VolunteerProfilesApi {
    private final RegisterVolunteerHandler registerVolunteer;
    private final ChangeVolunteerStatusHandler changeVolunteerStatus;
    private final CreateCredentialTypeHandler createCredentialType;
    private final RecordVolunteerCredentialHandler recordVolunteerCredential;
    private final RevokeVolunteerCredentialHandler revokeVolunteerCredential;
    private final SetVolunteerAvailabilityHandler setVolunteerAvailability;
    private final ListVolunteersHandler listVolunteers;
    private final GetVolunteerHandler getVolunteer;
    private final ListCredentialTypesHandler listCredentialTypes;
    public VolunteerProfilesController(RegisterVolunteerHandler registerVolunteer,
        ChangeVolunteerStatusHandler changeVolunteerStatus, CreateCredentialTypeHandler createCredentialType,
        RecordVolunteerCredentialHandler recordVolunteerCredential,
        RevokeVolunteerCredentialHandler revokeVolunteerCredential,
        SetVolunteerAvailabilityHandler setVolunteerAvailability, ListVolunteersHandler listVolunteers,
        GetVolunteerHandler getVolunteer, ListCredentialTypesHandler listCredentialTypes) {
        this.registerVolunteer = registerVolunteer;
        this.changeVolunteerStatus = changeVolunteerStatus;
        this.createCredentialType = createCredentialType;
        this.recordVolunteerCredential = recordVolunteerCredential;
        this.revokeVolunteerCredential = revokeVolunteerCredential;
        this.setVolunteerAvailability = setVolunteerAvailability;
        this.listVolunteers = listVolunteers;
        this.getVolunteer = getVolunteer;
        this.listCredentialTypes = listCredentialTypes;
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.manage')")
    public Created register(Register b, OAuth2AuthenticatedPrincipal p) {
        return new Created(registerVolunteer
            .handle(new RegisterVolunteerCommand(UUID.fromString(p.getName()), b.personId(), b.accountId())));
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.manage')")
    public void status(UUID id, Status b, OAuth2AuthenticatedPrincipal p) {
        changeVolunteerStatus.handle(
            new ChangeVolunteerStatusCommand(UUID.fromString(p.getName()), id, b.expectedVersion(), b.status()));
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.credentials.manage')")
    public Created type(CredentialTypeDraft b, OAuth2AuthenticatedPrincipal p) {
        return new Created(createCredentialType
            .handle(new CreateCredentialTypeCommand(UUID.fromString(p.getName()), b.code(), b.name())));
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.credentials.manage')")
    public Created credential(UUID id, CredentialDraft b, OAuth2AuthenticatedPrincipal p) {
        return new Created(
            recordVolunteerCredential.handle(new RecordVolunteerCredentialCommand(UUID.fromString(p.getName()), id,
                b.expectedVersion(), b.typeId(), b.validFrom(), b.validUntil(), b.evidence())));
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.credentials.manage')")
    public void revokeCredential(UUID id, CredentialRevoke b, OAuth2AuthenticatedPrincipal p) {
        revokeVolunteerCredential.handle(new RevokeVolunteerCredentialCommand(UUID.fromString(p.getName()), id,
            b.expectedVersion(), b.credentialId(), b.reason()));
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.manage')")
    public void availability(UUID id, Availability b, OAuth2AuthenticatedPrincipal p) {
        setVolunteerAvailability.handle(new SetVolunteerAvailabilityCommand(UUID.fromString(p.getName()), id,
            b.expectedVersion(), b.weekly(), b.exceptions()));
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.read')")
    public ServingViews.Volunteers volunteers(String search, int limit, int offset) {
        return listVolunteers.handle(new ListVolunteersQuery(search, limit, offset));
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.read')")
    public ServingViews.Profile volunteer(UUID id) {
        return getVolunteer.handle(new GetVolunteerQuery(id));
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.read')")
    public List<CredentialType> credentialTypes() {
        return listCredentialTypes.handle(new ListCredentialTypesQuery());
    }
}
