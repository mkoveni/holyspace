package org.onechurch.people.application.contract;
import java.time.LocalDate;
import java.util.Collection;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface PersonDirectory {
    record Entry(UUID id, String name, LocalDate birthDate, boolean available) {}
    List<Entry> getAll(Collection<UUID> ids);
    List<UUID> matchingIds(String search);
    Optional<Entry> get(UUID id);
    Optional<Entry> lock(UUID id);
}
