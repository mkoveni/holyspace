package org.onechurch.people.application;

import org.onechurch.people.application.contract.TitleReader.Title;
import org.onechurch.people.domain.MembershipType;

import java.util.List;

public record PeopleMetadata(List<Membership> membershipTypes, List<Title> titles, List<String> genders,
    List<String> lifeStatuses, List<String> relationshipTypes, int adultAge, String timeZone) {

    public record Membership(MembershipType code, String label) {}
}
