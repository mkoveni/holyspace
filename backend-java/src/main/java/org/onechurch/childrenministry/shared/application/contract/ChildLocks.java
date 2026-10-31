package org.onechurch.childrenministry.shared.application.contract;

import java.util.UUID;
public interface ChildLocks {
    void lock(UUID childId);
}
