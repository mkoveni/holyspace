package org.onechurch.giving.domain.repository;
import org.onechurch.giving.domain.DonorLink;

import java.util.Optional;
import java.util.UUID;
public interface DonorLinkRepository {
    Optional<DonorLink> lock(UUID account);
    void save(DonorLink link, String evidence);
    void online(UUID fundId, boolean enabled);
}
