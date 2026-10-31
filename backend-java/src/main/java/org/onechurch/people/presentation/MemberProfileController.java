package org.onechurch.people.presentation;
import org.onechurch.people.application.command.removemycontact.RemoveMyContactCommand;
import org.onechurch.people.application.command.removemycontact.RemoveMyContactHandler;
import org.onechurch.people.application.command.savemycontact.SaveMyContactCommand;
import org.onechurch.people.application.command.savemycontact.SaveMyContactHandler;
import org.onechurch.people.application.command.updatemyprofile.UpdateMyProfileCommand;
import org.onechurch.people.application.command.updatemyprofile.UpdateMyProfileHandler;
import org.onechurch.people.application.query.getmyprofile.GetMyProfileHandler;
import org.onechurch.people.application.query.getmyprofile.GetMyProfileQuery;
import org.onechurch.people.presentation.api.MemberProfileApi;
import org.springframework.http.CacheControl;
import org.springframework.http.ResponseEntity;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.UUID;
@RestController
@PreAuthorize("isAuthenticated()")
public class MemberProfileController implements MemberProfileApi {
    private final GetMyProfileHandler get;
    private final UpdateMyProfileHandler update;
    private final SaveMyContactHandler save;
    private final RemoveMyContactHandler remove;
    public MemberProfileController(GetMyProfileHandler get, UpdateMyProfileHandler update, SaveMyContactHandler save,
        RemoveMyContactHandler remove) {
        this.get = get;
        this.update = update;
        this.save = save;
        this.remove = remove;
    }
    @Override
    public ResponseEntity<GetMyProfileHandler.Result> profile() {
        return ResponseEntity.ok().cacheControl(CacheControl.noStore()).body(get.handle(new GetMyProfileQuery()));
    }
    @Override
    public void update(ProfileBody b, OAuth2AuthenticatedPrincipal actor) {
        update.handle(new UpdateMyProfileCommand(UUID.fromString(actor.getName()), b.titleCode(), b.firstName(),
            b.lastName(), b.gender(), b.expectedVersion()));
    }
    @Override
    public Created saveContact(ContactBody b, OAuth2AuthenticatedPrincipal actor) {
        return new Created(save.handle(new SaveMyContactCommand(UUID.fromString(actor.getName()), b.contactId(),
            b.kind(), b.value(), b.expectedVersion())));
    }
    @Override
    public void removeContact(UUID contactId, long expectedVersion, OAuth2AuthenticatedPrincipal actor) {
        remove.handle(new RemoveMyContactCommand(UUID.fromString(actor.getName()), contactId, expectedVersion));
    }
}
