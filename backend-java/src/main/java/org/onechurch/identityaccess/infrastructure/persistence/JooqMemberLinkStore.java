package org.onechurch.identityaccess.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.IDENTITY_MEMBER_LINKS;
import static org.onechurch.shared.infrastructure.jooq.Tables.IDENTITY_MEMBER_LINK_HISTORY;

import org.jooq.DSLContext;
import org.onechurch.identityaccess.application.contract.MemberLinkReader;
import org.onechurch.identityaccess.domain.MemberLink;
import org.onechurch.identityaccess.domain.repository.MemberLinkRepository;
import org.onechurch.shared.infrastructure.jooq.tables.records.IdentityMemberLinksRecord;
import org.springframework.stereotype.Repository;

import java.time.ZoneOffset;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
@Repository
public class JooqMemberLinkStore implements MemberLinkReader, MemberLinkRepository {
    private final DSLContext db;
    public JooqMemberLinkStore(DSLContext db) {
        this.db = db;
    }
    private MemberLink map(IdentityMemberLinksRecord r) {
        return new MemberLink(r.getAccountId(), r.getPersonId(), r.getActive(), r.getVersion(), r.getVerifiedBy(),
            r.getVerifiedAt().toInstant());
    }
    public Optional<MemberLink> memberLink(UUID account) {
        return db.selectFrom(IDENTITY_MEMBER_LINKS)
            .where(IDENTITY_MEMBER_LINKS.ACCOUNT_ID.eq(account))
            .fetchOptional()
            .map(this::map);
    }
    public List<MemberLink> memberLinks(int limit, int offset) {
        return db.selectFrom(IDENTITY_MEMBER_LINKS)
            .orderBy(IDENTITY_MEMBER_LINKS.VERIFIED_AT.desc(), IDENTITY_MEMBER_LINKS.ACCOUNT_ID)
            .limit(limit)
            .offset(offset)
            .fetch(this::map);
    }
    public Optional<MemberLink> lock(UUID account) {
        return db.selectFrom(IDENTITY_MEMBER_LINKS)
            .where(IDENTITY_MEMBER_LINKS.ACCOUNT_ID.eq(account))
            .forUpdate()
            .fetchOptional()
            .map(this::map);
    }
    public void save(MemberLink link, String evidence) {
        db.insertInto(IDENTITY_MEMBER_LINKS)
            .set(IDENTITY_MEMBER_LINKS.ACCOUNT_ID, link.accountId())
            .set(IDENTITY_MEMBER_LINKS.PERSON_ID, link.personId())
            .set(IDENTITY_MEMBER_LINKS.ACTIVE, link.active())
            .set(IDENTITY_MEMBER_LINKS.VERSION, link.version())
            .set(IDENTITY_MEMBER_LINKS.VERIFIED_BY, link.verifiedBy())
            .set(IDENTITY_MEMBER_LINKS.VERIFIED_AT, link.verifiedAt().atOffset(ZoneOffset.UTC))
            .onConflict(IDENTITY_MEMBER_LINKS.ACCOUNT_ID)
            .doUpdate()
            .set(IDENTITY_MEMBER_LINKS.ACTIVE, link.active())
            .set(IDENTITY_MEMBER_LINKS.VERSION, link.version())
            .set(IDENTITY_MEMBER_LINKS.VERIFIED_BY, link.verifiedBy())
            .set(IDENTITY_MEMBER_LINKS.VERIFIED_AT, link.verifiedAt().atOffset(ZoneOffset.UTC))
            .execute();
        db.insertInto(IDENTITY_MEMBER_LINK_HISTORY)
            .set(IDENTITY_MEMBER_LINK_HISTORY.ID, UUID.randomUUID())
            .set(IDENTITY_MEMBER_LINK_HISTORY.ACCOUNT_ID, link.accountId())
            .set(IDENTITY_MEMBER_LINK_HISTORY.PERSON_ID, link.personId())
            .set(IDENTITY_MEMBER_LINK_HISTORY.ACTIVE, link.active())
            .set(IDENTITY_MEMBER_LINK_HISTORY.VERSION, link.version())
            .set(IDENTITY_MEMBER_LINK_HISTORY.ACTOR_ID, link.verifiedBy())
            .set(IDENTITY_MEMBER_LINK_HISTORY.EVIDENCE, evidence)
            .set(IDENTITY_MEMBER_LINK_HISTORY.OCCURRED_AT, link.verifiedAt().atOffset(ZoneOffset.UTC))
            .execute();
    }
}
