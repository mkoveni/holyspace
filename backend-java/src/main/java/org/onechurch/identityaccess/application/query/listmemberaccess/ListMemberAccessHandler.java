package org.onechurch.identityaccess.application.query.listmemberaccess;
import org.onechurch.identityaccess.application.authorization.Authorization;
import org.onechurch.identityaccess.application.contract.AccountNames;
import org.onechurch.identityaccess.application.contract.MemberLinkReader;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.identityaccess.domain.MemberLink;
import org.onechurch.people.application.contract.PersonDirectory;

import java.util.List;
import java.util.Map;
import java.util.UUID;
public final class ListMemberAccessHandler {
    public record Entry(UUID accountId, String accountName, UUID personId, String personName, boolean active,
        long version) {}
    public record Page(List<Entry> links, boolean hasMore) {}
    private final Authorization auth;
    private final MemberLinkReader links;
    private final AccountNames names;
    private final PersonDirectory people;
    public ListMemberAccessHandler(Authorization auth, MemberLinkReader links, AccountNames names,
        PersonDirectory people) {
        this.auth = auth;
        this.links = links;
        this.names = names;
        this.people = people;
    }
    public Page handle(ListMemberAccessQuery q) {
        auth.requirePermission("identity.manage");
        auth.requirePermission("people.manage");
        if (q.limit() < 1 || q.limit() > 100 || q.offset() < 0 || q.offset() > 100000) {
            throw new IdentityException(IdentityException.Kind.INVALID, "Choose a valid page.");
        }
        List<MemberLink> rows = links.memberLinks(q.limit() + 1, q.offset());
        Map<UUID, String> labels = names.names(rows.stream().map(MemberLink::accountId).toList());
        return new Page(rows.stream()
            .limit(q.limit())
            .map(link -> new Entry(link.accountId(), labels.getOrDefault(link.accountId(), "Account unavailable"),
                link.personId(),
                people.get(link.personId()).map(PersonDirectory.Entry::name).orElse("Person unavailable"),
                link.active(), link.version()))
            .toList(), rows.size() > q.limit());
    }
}
