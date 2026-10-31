package org.onechurch.identityaccess.application.query.listmysessions;
import org.onechurch.identityaccess.application.authorization.Authorization;
import org.onechurch.identityaccess.application.contract.CurrentActor;
import org.onechurch.identityaccess.application.contract.DeviceSessionReader;
import org.onechurch.identityaccess.domain.IdentityException;

import java.time.Clock;
import java.time.Instant;
import java.util.List;
import java.util.UUID;
public final class ListMySessionsHandler {
    public record Device(UUID id, String deviceName, Instant createdAt, Instant expiresAt, boolean current) {}
    public record Page(List<Device> items, boolean hasMore) {}
    private final Authorization auth;
    private final CurrentActor current;
    private final DeviceSessionReader reader;
    private final Clock clock;
    public ListMySessionsHandler(Authorization auth, CurrentActor current, DeviceSessionReader reader, Clock clock) {
        this.auth = auth;
        this.current = current;
        this.reader = reader;
        this.clock = clock;
    }
    public Page handle(ListMySessionsQuery q) {
        UUID actor = auth.requireAccount();
        if (q.limit() < 1 || q.limit() > 100 || q.offset() < 0 || q.offset() > 100000) {
            throw new IdentityException(IdentityException.Kind.INVALID, "Choose a valid device page.");
        }
        UUID session = current.current().orElseThrow().sessionId();
        List<DeviceSessionReader.Device> rows = reader.devices(actor, clock.instant(), q.limit() + 1, q.offset());
        return new Page(rows.stream()
            .limit(q.limit())
            .map(d -> new Device(d.id(), d.deviceName(), d.createdAt(), d.expiresAt(), d.id().equals(session)))
            .toList(), rows.size() > q.limit());
    }
}
