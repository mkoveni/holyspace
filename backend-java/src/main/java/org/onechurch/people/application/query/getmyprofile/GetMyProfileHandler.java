package org.onechurch.people.application.query.getmyprofile;
import org.onechurch.identityaccess.application.contract.MemberIdentity;
import org.onechurch.people.application.contract.PersonReader;
import org.onechurch.people.application.contract.TitleReader;
import org.onechurch.people.domain.ContactMethod;
import org.onechurch.people.domain.PersonDetails;

import java.util.List;
import java.util.UUID;
public final class GetMyProfileHandler {
    public record Profile(UUID id, PersonDetails details, String membershipType, long version,
        List<ContactMethod> contacts) {}
    public record Result(Profile profile, List<TitleReader.Title> titles) {}
    private final MemberIdentity identity;
    private final PersonReader people;
    private final TitleReader titles;
    public GetMyProfileHandler(MemberIdentity identity, PersonReader people, TitleReader titles) {
        this.identity = identity;
        this.people = people;
        this.titles = titles;
    }
    public Result handle(GetMyProfileQuery q) {
        return identity.currentPerson()
            .flatMap(people::find)
            .map(p -> new Result(new Profile(p.id(), p.details(), p.membershipType().name(), p.version(),
                people.contacts(p.id()).stream().filter(c -> c.endedAt() == null).toList()), titles.titles()))
            .orElseGet(() -> new Result(null, List.of()));
    }
}
