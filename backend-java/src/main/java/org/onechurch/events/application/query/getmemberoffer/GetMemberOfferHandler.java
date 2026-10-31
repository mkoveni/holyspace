package org.onechurch.events.application.query.getmemberoffer;
import org.onechurch.events.application.contract.EventReader;
import org.onechurch.events.application.contract.MemberEventReader;
import org.onechurch.events.domain.EventException;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class GetMemberOfferHandler {
    private final PermissionChecker auth;
    private final MemberEventReader reader;
    private final EventReader events;
    public GetMemberOfferHandler(PermissionChecker auth, MemberEventReader reader, EventReader events) {
        this.auth = auth;
        this.reader = reader;
        this.events = events;
    }
    public MemberEventReader.Offer handle(GetMemberOfferQuery q) {
        auth.requirePermission("events.manage");
        events.event(q.eventId()).orElseThrow(() -> EventException.missing("Event"));
        return reader.offer(q.eventId());
    }
}
