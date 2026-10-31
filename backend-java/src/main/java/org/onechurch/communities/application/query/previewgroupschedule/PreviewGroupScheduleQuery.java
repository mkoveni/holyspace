package org.onechurch.communities.application.query.previewgroupschedule;
import org.onechurch.events.application.contract.CommunityCalendar;

import java.util.UUID;
public record PreviewGroupScheduleQuery(UUID communityId, CommunityCalendar.Schedule schedule) {}
