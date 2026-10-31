package org.onechurch.events.application.contract;
import java.util.UUID;
public interface MemberEventWriter {
    void offer(UUID event, boolean enabled, long version);
}
