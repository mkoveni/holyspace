package org.onechurch.people.application.command.updatemyprofile;
import org.onechurch.identityaccess.application.contract.MemberIdentity;
import org.onechurch.identityaccess.application.contract.MemberIdentityGuard;
import org.onechurch.people.application.contract.PeopleAuditWriter;
import org.onechurch.people.application.contract.TitleReader;
import org.onechurch.people.domain.PeopleException;
import org.onechurch.people.domain.Person;
import org.onechurch.people.domain.PersonDetails;
import org.onechurch.people.domain.repository.PersonRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.LocalDate;
import java.time.ZoneId;
public final class UpdateMyProfileHandler {
    private final MemberIdentity identity;
    private final MemberIdentityGuard guard;
    private final PersonRepository people;
    private final TitleReader titles;
    private final PeopleAuditWriter audit;
    private final UnitOfWork tx;
    private final Clock clock;
    private final ZoneId zone;
    public UpdateMyProfileHandler(MemberIdentity identity, MemberIdentityGuard guard, PersonRepository people,
        TitleReader titles, PeopleAuditWriter audit, UnitOfWork tx, Clock clock, ZoneId zone) {
        this.identity = identity;
        this.guard = guard;
        this.people = people;
        this.titles = titles;
        this.audit = audit;
        this.tx = tx;
        this.clock = clock;
        this.zone = zone;
    }
    public void handle(UpdateMyProfileCommand c) {
        identity.requirePerson(c.actorId());
        tx.execute(() -> {
            Person person = people.findForUpdate(guard.lockPerson(c.actorId()))
                .orElseThrow(() -> PeopleException.missing("Person"));
            identity.requirePerson(c.actorId());
            person.requireAvailable();
            if (c.titleCode() != null && !titles.exists(c.titleCode())) {
                throw PeopleException.invalid("Select an existing title.");
            }
            PersonDetails original = person.details();
            PersonDetails details = new PersonDetails(c.titleCode(), c.firstName(), c.lastName(),
                original.dateOfBirth(), c.gender(), original.lifeStatus(), original.dateOfDeath());
            people.update(person.update(details, person.membershipType(), c.expectedVersion(),
                LocalDate.now(clock.withZone(zone)), clock.instant()));
            audit.audit(c.actorId(), "person.self_updated", person.id(), clock.instant());
            return null;
        });
    }
}
