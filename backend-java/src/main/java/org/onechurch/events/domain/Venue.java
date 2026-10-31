package org.onechurch.events.domain;
import java.util.UUID;
public record Venue(UUID id, String name, String address, boolean exclusive, boolean active, long version) {
    public Venue {
        EventRules.id(id);
        name = EventRules.text(name, 150);
        address = EventRules.optional(address, 1000);
    }
    public Venue edit(String name, String address, boolean active) {
        return new Venue(id, name, address, exclusive, active, version + 1);
    }
}
