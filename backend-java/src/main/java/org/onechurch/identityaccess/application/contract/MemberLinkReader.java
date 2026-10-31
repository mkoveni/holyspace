package org.onechurch.identityaccess.application.contract;
import org.onechurch.identityaccess.domain.MemberLink;

import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface MemberLinkReader {
    Optional<MemberLink> memberLink(UUID account);
    List<MemberLink> memberLinks(int limit, int offset);
}
