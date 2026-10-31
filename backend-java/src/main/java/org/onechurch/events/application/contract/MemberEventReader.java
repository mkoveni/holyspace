package org.onechurch.events.application.contract;
import java.time.Instant;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface MemberEventReader {
    record Offer(boolean enabled, long version) {}
    record Booking(UUID id, String status, long version, String attendance) {}
    record Item(UUID id, UUID eventId, String title, String description, Instant startsAt, Instant endsAt,
        String timeZone, String venueName, String venueAddress, String status, boolean registrationOpen,
        boolean waitlistEnabled, Integer remainingPlaces, Booking booking) {}
    record Page(boolean verified, List<Item> items, boolean hasMore) {}
    Offer offer(UUID event);
    List<Item> list(UUID person, Instant from, Instant until, boolean mine, int limit, int offset);
    Optional<Item> detail(UUID person, UUID occurrence);
}
