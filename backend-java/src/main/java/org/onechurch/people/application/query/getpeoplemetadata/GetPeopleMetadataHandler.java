package org.onechurch.people.application.query.getpeoplemetadata;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.PeopleMetadata;
import org.onechurch.people.application.contract.TitleReader;
import org.onechurch.people.domain.AgePolicy;
import org.onechurch.people.domain.Gender;
import org.onechurch.people.domain.LifeStatus;
import org.onechurch.people.domain.MembershipType;
import org.onechurch.people.domain.RelationshipType;

import java.time.ZoneId;
import java.util.Arrays;

public final class GetPeopleMetadataHandler {

    private final PermissionChecker authorization;
    private final TitleReader titles;
    private final ZoneId zone;

    public GetPeopleMetadataHandler(PermissionChecker authorization, TitleReader titles, ZoneId zone) {
        this.authorization = authorization;
        this.titles = titles;
        this.zone = zone;
    }

    public PeopleMetadata handle(GetPeopleMetadataQuery command) {
        authorization.requirePermission("people.read");
        return new PeopleMetadata(
            Arrays.stream(MembershipType.values())
                .map(type -> new PeopleMetadata.Membership(type, type.label()))
                .toList(),
            titles.titles(), Arrays.stream(Gender.values()).map(Enum::name).toList(),
            Arrays.stream(LifeStatus.values()).map(Enum::name).toList(),
            Arrays.stream(RelationshipType.values()).map(Enum::name).toList(), AgePolicy.ADULT_AGE, zone.getId());
    }
}
