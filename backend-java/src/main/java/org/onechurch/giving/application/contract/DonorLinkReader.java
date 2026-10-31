package org.onechurch.giving.application.contract;
import org.onechurch.giving.domain.DonorLink;

import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface DonorLinkReader {
    Optional<DonorLink> link(UUID accountId);
    List<DonorLink> links(int limit, int offset);
}
