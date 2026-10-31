package org.onechurch.giving.application.contract;
import java.util.List;
import java.util.UUID;
public interface OnlineFundDirectory {
    record Entry(UUID id, String name, String description, boolean active, boolean enabled, long version) {}
    List<Entry> funds(boolean management);
    boolean enabled(UUID id);
}
