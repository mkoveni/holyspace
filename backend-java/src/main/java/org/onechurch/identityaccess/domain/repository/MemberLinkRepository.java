package org.onechurch.identityaccess.domain.repository;
import org.onechurch.identityaccess.domain.MemberLink;

import java.util.Optional;
import java.util.UUID;
public interface MemberLinkRepository {
    Optional<MemberLink> lock(UUID account);
    void save(MemberLink link, String evidence);
}
