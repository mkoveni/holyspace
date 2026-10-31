package org.onechurch.identityaccess.infrastructure;
import org.onechurch.identityaccess.application.contract.MemberAdmissionGuard;
import org.onechurch.identityaccess.domain.MemberLink;
import org.onechurch.identityaccess.domain.repository.MemberLinkRepository;
import org.springframework.stereotype.Component;

import java.util.Optional;
import java.util.UUID;
@Component
public class PublishedMemberAdmissionGuard implements MemberAdmissionGuard {
    private final MemberLinkRepository links;
    public PublishedMemberAdmissionGuard(MemberLinkRepository links) {
        this.links = links;
    }
    public Optional<Identity> lock(UUID account) {
        return links.lock(account)
            .filter(MemberLink::active)
            .map(link -> new Identity(link.personId(), link.version()));
    }
}
