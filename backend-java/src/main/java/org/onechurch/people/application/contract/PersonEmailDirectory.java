package org.onechurch.people.application.contract;
import java.util.List;
import java.util.UUID;
public interface PersonEmailDirectory {
    record Email(UUID id, String value) {}
    List<Email> active(UUID personId);
}
