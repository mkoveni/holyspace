package org.onechurch.communities.application;
import org.onechurch.communities.application.contract.CommunityReader;
import org.onechurch.communities.application.contract.CommunityWriter;
import org.onechurch.communities.domain.Community;
import org.onechurch.communities.domain.CommunityException;
import org.onechurch.communities.domain.CommunityRules;
import org.onechurch.communities.domain.Leadership;
import org.onechurch.communities.domain.Membership;
import org.onechurch.communities.domain.repository.CommunityRepository;
import org.onechurch.identityaccess.application.contract.AccountDirectory;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.util.Optional;
import java.util.UUID;
public final class CommunityManagement {
    private final CommunityReader reader;
    private final CommunityWriter writer;
    private final CommunityRepository repository;
    private final PersonDirectory people;
    private final AccountDirectory accounts;
    private final PermissionChecker auth;
    private final CommunityAccess access;
    private final UnitOfWork tx;
    private final Clock clock;
    public CommunityManagement(CommunityReader reader, CommunityWriter writer, CommunityRepository repository,
        PersonDirectory people, AccountDirectory accounts, PermissionChecker auth, CommunityAccess access,
        UnitOfWork tx, Clock clock) {
        this.reader = reader;
        this.writer = writer;
        this.repository = repository;
        this.people = people;
        this.accounts = accounts;
        this.auth = auth;
        this.access = access;
        this.tx = tx;
        this.clock = clock;
    }
    private Community lock(UUID id) {
        return repository.lockCommunity(id).orElseThrow(CommunityException::missing);
    }
    private void active(Community c) {
        if (!c.active()) {
            throw CommunityException.conflict("This community is archived.");
        }
    }
    private Optional<UUID> previous(UUID request, String hash) {
        if (request == null) {
            throw CommunityException.invalid("A request identifier is required.");
        }
        return reader.request(request).map(r -> {
            if (!r.hash().equals(hash)) {
                throw CommunityException.conflict("This request identifier was used for different details.");
            }
            return r.target();
        });
    }
    public UUID create(UUID actor, UUID request, String kind, String name, String description, UUID parent,
        String campus) {
        Community validated = new Community(UUID.randomUUID(), kind, name, description, parent, campus, true, 0);
        String hash = CommunityHash.of(actor, "CREATE", kind, name, description, parent, campus);
        return tx.execute(() -> {
            access.manager(actor);
            Optional<UUID> old = previous(request, hash);
            if (old.isPresent()) {
                return old.orElseThrow();
            }
            if (parent != null) {
                Community ministry = lock(parent);
                if (!ministry.kind().equals("MINISTRY")) {
                    throw CommunityException.invalid("Choose a parent ministry.");
                }
                active(ministry);
            }
            repository.insert(validated);
            writer.request(request, hash, validated.id());
            writer.audit(validated.id(), validated.id(), "CREATED", "", actor, clock.instant());
            return validated.id();
        });
    }
    public void update(UUID actor, UUID id, long version, String name, String description, UUID parent, String campus,
        boolean active, String reason) {
        String note = CommunityRules.text(reason, 2000);
        tx.execute(() -> {
            // Ministry locks precede group locks when validating a parent association.
            if (parent != null) {
                Community ministry = lock(parent);
                if (!ministry.kind().equals("MINISTRY") || !ministry.active()) {
                    throw CommunityException.conflict("Choose an active parent ministry.");
                }
            }
            Community c = lock(id);
            access.manager(actor);
            CommunityRules.version(c.version(), version);
            repository.save(c.update(name, description, parent, campus, active));
            writer.audit(id, id, active ? "UPDATED" : "ARCHIVED", note, actor, clock.instant());
            return null;
        });
    }
    public UUID join(UUID actor, UUID request, UUID community, UUID person) {
        String hash = CommunityHash.of(actor, "JOIN", community, person);
        return tx.execute(() -> {
            people.lock(person)
                .filter(PersonDirectory.Entry::available)
                .orElseThrow(() -> CommunityException.invalid("Choose an available person."));
            Community c = lock(community);
            access.manager(actor);
            Optional<UUID> old = previous(request, hash);
            if (old.isPresent()) {
                return old.orElseThrow();
            }
            active(c);
            Instant now = CommunityRules.time(clock.instant());
            if (reader.currentMembership(community, person, now).isPresent()) {
                throw CommunityException.conflict("This person is already a current member.");
            }
            Membership member = new Membership(UUID.randomUUID(), community, person, now, null, 0);
            repository.insert(member);
            writer.request(request, hash, member.id());
            writer.audit(community, member.id(), "MEMBER_JOINED", "", actor, now);
            return member.id();
        });
    }
    public void leave(UUID actor, UUID id, long version, String reason) {
        String note = CommunityRules.text(reason, 2000);
        tx.execute(() -> {
            Membership before = reader.membership(id).orElseThrow(CommunityException::missing);
            people.lock(before.personId()).orElseThrow(CommunityException::missing);
            lock(before.communityId());
            access.manager(actor);
            Membership member = reader.membership(id).orElseThrow();
            CommunityRules.version(member.version(), version);
            Instant now = CommunityRules.time(clock.instant());
            repository.save(member.end(now));
            reader.activeLeadership(id).ifPresent(l -> repository.save(l.end(now)));
            writer.audit(member.communityId(), id, "MEMBER_LEFT", note, actor, now);
            return null;
        });
    }
    public UUID appoint(UUID actor, UUID request, UUID membership, long version, String role, UUID account,
        String reason) {
        String note = CommunityRules.text(reason, 2000);
        String hash = CommunityHash.of(actor, "APPOINT", membership, version, role, account, note);
        return tx.execute(() -> {
            Membership before = reader.membership(membership).orElseThrow(CommunityException::missing);
            people.lock(before.personId())
                .filter(PersonDirectory.Entry::available)
                .orElseThrow(CommunityException::missing);
            Community c = lock(before.communityId());
            access.manager(actor);
            Optional<UUID> old = previous(request, hash);
            if (old.isPresent()) {
                return old.orElseThrow();
            }
            if (account != null) {
                auth.requirePermission(actor, "identity.manage");
                if (!accounts.active(account)) {
                    throw CommunityException.invalid("Choose an active login account.");
                }
            }
            active(c);
            Membership member = reader.membership(membership).orElseThrow();
            CommunityRules.version(member.version(), version);
            Instant now = CommunityRules.time(clock.instant());
            if (!member.covers(now)) {
                throw CommunityException.conflict("Leadership requires a current membership.");
            }
            if (reader.activeLeadership(membership).isPresent()) {
                throw CommunityException.conflict("End the current appointment before creating another.");
            }
            Leadership leader = new Leadership(UUID.randomUUID(), membership, c.id(), account, role, now, null, 0);
            repository.insert(leader);
            writer.request(request, hash, leader.id());
            writer.audit(c.id(), leader.id(), "LEADER_APPOINTED", note, actor, now);
            return leader.id();
        });
    }
    public void endLeadership(UUID actor, UUID id, long version, String reason) {
        String note = CommunityRules.text(reason, 2000);
        tx.execute(() -> {
            Leadership before = reader.leadership(id).orElseThrow(CommunityException::missing);
            Membership member = reader.membership(before.membershipId()).orElseThrow();
            people.lock(member.personId()).orElseThrow(CommunityException::missing);
            lock(before.communityId());
            access.manager(actor);
            Leadership leader = reader.leadership(id).orElseThrow();
            CommunityRules.version(leader.version(), version);
            repository.save(leader.end(clock.instant()));
            writer.audit(leader.communityId(), id, "LEADERSHIP_ENDED", note, actor, clock.instant());
            return null;
        });
    }
}
