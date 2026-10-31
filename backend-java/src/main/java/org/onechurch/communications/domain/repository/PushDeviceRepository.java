package org.onechurch.communications.domain.repository;
import org.onechurch.communications.domain.PushDevice;

import java.util.Optional;
import java.util.UUID;
public interface PushDeviceRepository {
    void ensure(PushDevice device);
    Optional<PushDevice> lock(UUID id);
    void save(PushDevice device);
}
