package org.onechurch.communities.application;
import org.onechurch.communities.application.contract.MemberCommunityReader;
import org.onechurch.communities.domain.CommunityException;
import org.onechurch.communities.domain.CommunityRules;
import org.onechurch.events.application.contract.CommunityCalendar;
import org.onechurch.events.application.contract.CommunityScheduleReader;
import org.onechurch.people.application.contract.PersonDirectory;

import java.time.Instant;
import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.stream.Collectors;
public final class MemberCommunityViews {
    public record MemberCommunitiesPage(boolean verified, List<MemberCommunityReader.MemberCommunity> items,
        boolean hasMore) {}
    public record MemberCommunityLeader(String name, String role) {}
    public record MemberCommunityDetails(MemberCommunityReader.MemberCommunity community,
        List<MemberCommunityLeader> leaders, boolean hasMoreLeaders) {}
    public record MemberGroupMeeting(UUID id, UUID communityId, String communityName, Instant startsAt, Instant endsAt,
        String timeZone, String venue, String status, String attendance) {}
    public record MemberGroupMeetingsPage(boolean verified, List<MemberGroupMeeting> items, boolean hasMore) {}
    private final MemberCommunityReader reader;
    private final PersonDirectory people;
    private final CommunityScheduleReader calendar;
    public MemberCommunityViews(MemberCommunityReader reader, PersonDirectory people,
        CommunityScheduleReader calendar) {
        this.reader = reader;
        this.people = people;
        this.calendar = calendar;
    }
    public MemberCommunityDetails details(UUID person, UUID community, Instant at, int limit, int offset) {
        CommunityRules.page(limit, offset);
        MemberCommunityReader.MemberCommunity own =
            reader.community(person, community, at).orElseThrow(CommunityException::missing);
        List<MemberCommunityReader.Leader> page = reader.leaders(person, community, at, limit + 1, offset);
        Map<UUID, String> names =
            people.getAll(page.stream().map(MemberCommunityReader.Leader::personId).distinct().toList())
                .stream()
                .filter(PersonDirectory.Entry::available)
                .collect(Collectors.toMap(PersonDirectory.Entry::id, PersonDirectory.Entry::name));
        return new MemberCommunityDetails(own,
            page.stream()
                .limit(limit)
                .filter(leader -> names.containsKey(leader.personId()))
                .map(leader -> new MemberCommunityLeader(names.get(leader.personId()), leader.role()))
                .toList(),
            page.size() > limit);
    }
    public List<MemberGroupMeeting> resolve(List<MemberCommunityReader.Meeting> rows) {
        Map<UUID, CommunityCalendar.Date> dates =
            calendar.dates(rows.stream().map(MemberCommunityReader.Meeting::occurrenceId).distinct().toList())
                .stream()
                .collect(Collectors.toMap(CommunityCalendar.Date::id, date -> date));
        return rows.stream().map(row -> {
            CommunityCalendar.Date date = dates.get(row.occurrenceId());
            if (date == null) {
                throw CommunityException
                    .conflict("A meeting schedule is unavailable. Please refresh or contact the church office.");
            }
            return new MemberGroupMeeting(row.id(), row.communityId(), row.communityName(), date.startsAt(),
                date.endsAt(), date.timeZone(), date.venue(),
                date.status().equals("CANCELLED") ? "CANCELLED" : row.status(), row.attendance());
        }).toList();
    }
}
