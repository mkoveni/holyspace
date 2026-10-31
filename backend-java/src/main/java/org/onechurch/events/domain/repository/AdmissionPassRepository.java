package org.onechurch.events.domain.repository;
import org.onechurch.events.domain.AdmissionPass;

import java.time.Instant;
import java.util.Optional;
public interface AdmissionPassRepository {
    void insert(AdmissionPass pass);
    Optional<AdmissionPass> find(String hash);
    Optional<AdmissionPass> lock(String hash);
    void used(String hash, Instant now);
}
