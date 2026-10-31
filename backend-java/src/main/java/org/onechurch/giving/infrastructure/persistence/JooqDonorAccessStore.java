package org.onechurch.giving.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_DONOR_LINKS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_DONOR_LINK_HISTORY;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_FUNDS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_ONLINE_FUNDS;

import org.jooq.DSLContext;
import org.jooq.impl.DSL;
import org.onechurch.giving.application.contract.DonorLinkReader;
import org.onechurch.giving.application.contract.OnlineFundDirectory;
import org.onechurch.giving.domain.DonorLink;
import org.onechurch.giving.domain.repository.DonorLinkRepository;
import org.onechurch.shared.infrastructure.jooq.tables.records.GivingDonorLinksRecord;
import org.springframework.stereotype.Repository;

import java.time.ZoneOffset;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
@Repository
public class JooqDonorAccessStore implements DonorLinkReader, DonorLinkRepository, OnlineFundDirectory {
    private final DSLContext db;
    public JooqDonorAccessStore(DSLContext db) {
        this.db = db;
    }
    private DonorLink map(GivingDonorLinksRecord r) {
        return new DonorLink(r.getAccountId(), r.getPersonId(), r.getActive(), r.getVersion(), r.getVerifiedBy(),
            r.getVerifiedAt().toInstant());
    }
    public Optional<DonorLink> link(UUID account) {
        return db.selectFrom(GIVING_DONOR_LINKS)
            .where(GIVING_DONOR_LINKS.ACCOUNT_ID.eq(account))
            .fetchOptional()
            .map(this::map);
    }
    public Optional<DonorLink> lock(UUID account) {
        return db.selectFrom(GIVING_DONOR_LINKS)
            .where(GIVING_DONOR_LINKS.ACCOUNT_ID.eq(account))
            .forUpdate()
            .fetchOptional()
            .map(this::map);
    }
    public List<DonorLink> links(int limit, int offset) {
        return db.selectFrom(GIVING_DONOR_LINKS)
            .orderBy(GIVING_DONOR_LINKS.VERIFIED_AT.desc(), GIVING_DONOR_LINKS.ACCOUNT_ID)
            .limit(limit)
            .offset(offset)
            .fetch(this::map);
    }
    public void save(DonorLink link, String evidence) {
        db.insertInto(GIVING_DONOR_LINKS)
            .set(GIVING_DONOR_LINKS.ACCOUNT_ID, link.accountId())
            .set(GIVING_DONOR_LINKS.PERSON_ID, link.personId())
            .set(GIVING_DONOR_LINKS.ACTIVE, link.active())
            .set(GIVING_DONOR_LINKS.VERSION, link.version())
            .set(GIVING_DONOR_LINKS.VERIFIED_BY, link.verifiedBy())
            .set(GIVING_DONOR_LINKS.VERIFIED_AT, link.verifiedAt().atOffset(ZoneOffset.UTC))
            .onConflict(GIVING_DONOR_LINKS.ACCOUNT_ID)
            .doUpdate()
            .set(GIVING_DONOR_LINKS.ACTIVE, link.active())
            .set(GIVING_DONOR_LINKS.VERSION, link.version())
            .set(GIVING_DONOR_LINKS.VERIFIED_BY, link.verifiedBy())
            .set(GIVING_DONOR_LINKS.VERIFIED_AT, link.verifiedAt().atOffset(ZoneOffset.UTC))
            .execute();
        db.insertInto(GIVING_DONOR_LINK_HISTORY)
            .set(GIVING_DONOR_LINK_HISTORY.ID, UUID.randomUUID())
            .set(GIVING_DONOR_LINK_HISTORY.ACCOUNT_ID, link.accountId())
            .set(GIVING_DONOR_LINK_HISTORY.PERSON_ID, link.personId())
            .set(GIVING_DONOR_LINK_HISTORY.ACTIVE, link.active())
            .set(GIVING_DONOR_LINK_HISTORY.VERSION, link.version())
            .set(GIVING_DONOR_LINK_HISTORY.ACTOR_ID, link.verifiedBy())
            .set(GIVING_DONOR_LINK_HISTORY.EVIDENCE, evidence)
            .set(GIVING_DONOR_LINK_HISTORY.OCCURRED_AT, link.verifiedAt().atOffset(ZoneOffset.UTC))
            .execute();
    }
    public void online(UUID id, boolean enabled) {
        db.insertInto(GIVING_ONLINE_FUNDS)
            .set(GIVING_ONLINE_FUNDS.FUND_ID, id)
            .set(GIVING_ONLINE_FUNDS.ENABLED, enabled)
            .onConflict(GIVING_ONLINE_FUNDS.FUND_ID)
            .doUpdate()
            .set(GIVING_ONLINE_FUNDS.ENABLED, enabled)
            .execute();
    }
    public List<Entry> funds(boolean management) {
        return db
            .select(GIVING_FUNDS.ID, GIVING_FUNDS.NAME, GIVING_FUNDS.DESCRIPTION, GIVING_FUNDS.ACTIVE,
                GIVING_FUNDS.VERSION, GIVING_ONLINE_FUNDS.ENABLED)
            .from(GIVING_FUNDS)
            .leftJoin(GIVING_ONLINE_FUNDS)
            .on(GIVING_ONLINE_FUNDS.FUND_ID.eq(GIVING_FUNDS.ID))
            .where(management ? DSL.trueCondition()
                : GIVING_FUNDS.ACTIVE.isTrue().and(GIVING_ONLINE_FUNDS.ENABLED.isTrue()))
            .orderBy(GIVING_FUNDS.NAME, GIVING_FUNDS.ID)
            .limit(501)
            .fetch(r -> new Entry(r.get(GIVING_FUNDS.ID), r.get(GIVING_FUNDS.NAME), r.get(GIVING_FUNDS.DESCRIPTION),
                r.get(GIVING_FUNDS.ACTIVE), Boolean.TRUE.equals(r.get(GIVING_ONLINE_FUNDS.ENABLED)),
                r.get(GIVING_FUNDS.VERSION)));
    }
    public boolean enabled(UUID id) {
        return db.fetchExists(db.selectOne()
            .from(GIVING_FUNDS)
            .join(GIVING_ONLINE_FUNDS)
            .on(GIVING_ONLINE_FUNDS.FUND_ID.eq(GIVING_FUNDS.ID))
            .where(GIVING_FUNDS.ID.eq(id).and(GIVING_FUNDS.ACTIVE.isTrue()).and(GIVING_ONLINE_FUNDS.ENABLED.isTrue())));
    }
}
