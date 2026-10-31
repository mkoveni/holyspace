package org.onechurch.giving.application.query.listdonoraccess;
import org.onechurch.giving.application.contract.DonorLinkReader;
import org.onechurch.giving.application.contract.OnlineFundDirectory;
import org.onechurch.giving.domain.DonorLink;
import org.onechurch.giving.domain.GivingException;
import org.onechurch.identityaccess.application.contract.AccountNames;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonDirectory;

import java.util.List;
import java.util.UUID;
public final class ListDonorAccessHandler {
    public record Link(UUID accountId, String accountName, UUID personId, String personName, boolean active,
        long version) {}
    public record Result(List<Link> links, List<OnlineFundDirectory.Entry> funds, boolean hasMore) {}
    private final PermissionChecker auth;
    private final DonorLinkReader reader;
    private final OnlineFundDirectory funds;
    private final PersonDirectory people;
    private final AccountNames names;
    public ListDonorAccessHandler(PermissionChecker auth, DonorLinkReader reader, OnlineFundDirectory funds,
        PersonDirectory people, AccountNames names) {
        this.auth = auth;
        this.reader = reader;
        this.funds = funds;
        this.people = people;
        this.names = names;
    }
    public Result handle(ListDonorAccessQuery q) {
        auth.requirePermission("identity.manage");
        auth.requirePermission("giving.manage");
        if (q.limit() < 1 || q.limit() > 100 || q.offset() < 0 || q.offset() > 100000) {
            throw GivingException.invalid("Choose a valid page.");
        }
        List<DonorLink> rows = reader.links(q.limit() + 1, q.offset());
        List<Link> links = rows.stream()
            .limit(q.limit())
            .map(l -> new Link(l.accountId(),
                names.names(List.of(l.accountId())).getOrDefault(l.accountId(), "Account unavailable"), l.personId(),
                people.get(l.personId()).map(PersonDirectory.Entry::name).orElse("Person unavailable"), l.active(),
                l.version()))
            .toList();
        List<OnlineFundDirectory.Entry> catalog = funds.funds(true);
        if (catalog.size() > 500) {
            throw GivingException.conflict("The fund catalogue exceeds this view. Contact support.");
        }
        return new Result(links, catalog, rows.size() > q.limit());
    }
}
