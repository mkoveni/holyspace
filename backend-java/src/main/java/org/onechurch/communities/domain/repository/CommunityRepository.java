package org.onechurch.communities.domain.repository;
import org.onechurch.communities.domain.Community;
import org.onechurch.communities.domain.GroupMeeting;
import org.onechurch.communities.domain.Leadership;
import org.onechurch.communities.domain.Membership;

import java.util.Optional;
import java.util.UUID;
public interface CommunityRepository {
    Optional<Community> lockCommunity(UUID id);
    Optional<GroupMeeting> lockMeeting(UUID id);
    void insert(Community community);
    void save(Community community);
    void insert(Membership membership);
    void save(Membership membership);
    void insert(Leadership leadership);
    void save(Leadership leadership);
    void insert(GroupMeeting meeting);
    void save(GroupMeeting meeting);
}
