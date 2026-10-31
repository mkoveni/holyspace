package org.onechurch.events.application.query.getmyevent;
import org.onechurch.events.application.contract.MemberEventReader;
import org.onechurch.events.domain.EventException;
import org.onechurch.identityaccess.application.contract.MemberIdentity;

import java.time.Clock;
import java.util.UUID;
public final class GetMyEventHandler {
    private final MemberIdentity identity;
    private final MemberEventReader reader;
    private final Clock clock;
    public GetMyEventHandler(MemberIdentity identity, MemberEventReader reader, Clock clock) {
        this.identity = identity;
        this.reader = reader;
        this.clock = clock;
    }
    public MemberEventReader.Item handle(GetMyEventQuery q) {
        UUID person = identity.currentPerson().orElseThrow(() -> EventException.missing("Member event"));
        return reader.detail(person, q.occurrenceId()).orElseThrow(() -> EventException.missing("Member event"));
    }
}
