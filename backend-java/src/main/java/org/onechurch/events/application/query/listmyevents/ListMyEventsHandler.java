package org.onechurch.events.application.query.listmyevents;
import org.onechurch.events.application.contract.MemberEventReader;
import org.onechurch.events.domain.EventRules;
import org.onechurch.identityaccess.application.contract.MemberIdentity;

import java.time.Clock;
import java.time.Instant;
import java.util.List;
public final class ListMyEventsHandler {
    private final MemberIdentity identity;
    private final MemberEventReader reader;
    private final Clock clock;
    public ListMyEventsHandler(MemberIdentity identity, MemberEventReader reader, Clock clock) {
        this.identity = identity;
        this.reader = reader;
        this.clock = clock;
    }
    public MemberEventReader.Page handle(ListMyEventsQuery q) {
        EventRules.page(q.limit(), q.offset());
        return identity.currentPerson().map(person -> {
            Instant now = clock.instant();
            List<MemberEventReader.Item> rows =
                reader.list(person, now, now.plusSeconds(180L * 86400), q.mine(), q.limit() + 1, q.offset());
            return new MemberEventReader.Page(true, rows.stream().limit(q.limit()).toList(), rows.size() > q.limit());
        }).orElseGet(() -> new MemberEventReader.Page(false, List.of(), false));
    }
}
