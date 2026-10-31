package org.onechurch.childrenministry.safeguarding.domain.repository;

import org.onechurch.childrenministry.safeguarding.domain.SupportRecord;

import java.time.LocalDate;
import java.util.Optional;
import java.util.UUID;
public interface SupportRepository {
    Optional<SupportRecord> supportRecordForUpdate(UUID id);
    void insertSupportRecord(SupportRecord value);
    void updateSupportRecord(SupportRecord value);
    void purge(UUID childId, LocalDate before);
}
