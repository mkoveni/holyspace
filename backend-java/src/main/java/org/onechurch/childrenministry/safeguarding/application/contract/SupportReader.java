package org.onechurch.childrenministry.safeguarding.application.contract;

import org.onechurch.childrenministry.safeguarding.domain.SupportRecord;

import java.time.LocalDate;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface SupportReader {
    Optional<SupportRecord> supportRecord(UUID id);
    List<UUID> expiredSupport(LocalDate date, int limit);
}
