package org.onechurch.identityaccess.application.contract;
import java.util.List;
import java.util.UUID;
public interface ResponsibleAccountDirectory {
    record Entry(UUID id, String name) {}
    boolean eligible(UUID id, String permission);
    List<Entry> search(String permission, String search);
}
