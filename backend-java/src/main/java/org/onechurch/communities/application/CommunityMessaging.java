package org.onechurch.communities.application;
import org.onechurch.communications.application.contract.DeliveryReader;
import org.onechurch.communications.application.contract.DeliverySnapshot;
import org.onechurch.communications.application.contract.EmailDeliveries;
import org.onechurch.communications.application.contract.EmailTemplates;
import org.onechurch.communities.application.contract.CommunityReader;
import org.onechurch.communities.application.contract.CommunityWriter;
import org.onechurch.communities.domain.Community;
import org.onechurch.communities.domain.CommunityException;
import org.onechurch.communities.domain.CommunityRules;
import org.onechurch.communities.domain.Membership;
import org.onechurch.communities.domain.repository.CommunityRepository;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.people.application.contract.PersonEmailDirectory;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.util.List;
import java.util.Map;
import java.util.Objects;
import java.util.Optional;
import java.util.UUID;
public final class CommunityMessaging {
    public record Review(String hash, String recipient, String subject, String html, long templateVersion) {
        @Override
        public String toString() {
            return "CommunityNoticeReview[hash=" + hash + "]";
        }
    }
    private final CommunityReader reader;
    private final CommunityWriter writer;
    private final CommunityRepository repository;
    private final CommunityAccess access;
    private final PersonDirectory people;
    private final PersonEmailDirectory emails;
    private final EmailTemplates templates;
    private final EmailDeliveries deliveries;
    private final UnitOfWork tx;
    private final Clock clock;
    private final DeliveryReader deliveryReader;
    public CommunityMessaging(CommunityReader reader, CommunityWriter writer, CommunityRepository repository,
        CommunityAccess access, PersonDirectory people, PersonEmailDirectory emails, EmailTemplates templates,
        EmailDeliveries deliveries, UnitOfWork tx, Clock clock, DeliveryReader deliveryReader) {
        this.reader = reader;
        this.writer = writer;
        this.repository = repository;
        this.access = access;
        this.people = people;
        this.emails = emails;
        this.templates = templates;
        this.deliveries = deliveries;
        this.tx = tx;
        this.clock = clock;
        this.deliveryReader = deliveryReader;
    }
    public List<DeliveryReader.Summary> deliveries(UUID community, int limit, int offset) {
        access.require(community);
        CommunityRules.page(limit, offset);
        List<UUID> ids = reader.notices(community, limit, offset).stream().map(CommunityReader.Notice::id).toList();
        Map<UUID, DeliveryReader.Summary> summaries = deliveryReader.summaries(ids);
        return ids.stream().map(summaries::get).filter(Objects::nonNull).toList();
    }
    private Membership current(UUID membership) {
        Membership member = reader.membership(membership).orElseThrow(CommunityException::missing);
        Community c = access.community(member.communityId());
        if (!member.covers(clock.instant()) || !c.active()
            || !people.get(member.personId()).map(PersonDirectory.Entry::available).orElse(false)) {
            throw CommunityException.conflict("Notices require a current, available member of an active community.");
        }
        return member;
    }
    public List<PersonEmailDirectory.Email> contacts(UUID membership) {
        Membership m = current(membership);
        access.require(m.communityId());
        return emails.active(m.personId());
    }
    public static String hash(Membership m, Community c, UUID contact, String recipient, String subject, String html,
        long version) {
        return CommunityHash.of(m.id(), m.version(), c.id(), c.version(), contact, recipient, subject, html, version);
    }
    public Review review(UUID membership, UUID contact, String message) {
        Membership m = current(membership);
        access.require(m.communityId());
        Community c = access.community(m.communityId());
        String recipient = emails.active(m.personId())
            .stream()
            .filter(e -> e.id().equals(contact))
            .map(PersonEmailDirectory.Email::value)
            .findFirst()
            .orElseThrow(() -> CommunityException.conflict("Choose this member's current email address."));
        EmailTemplates.Rendered rendered =
            templates.render("communities.notice", Map.of("name", people.get(m.personId()).orElseThrow().name(),
                "community", c.name(), "message", CommunityRules.text(message, 5000)));
        return new Review(hash(m, c, contact, recipient, rendered.subject(), rendered.html(), rendered.version()),
            recipient, rendered.subject(), rendered.html(), rendered.version());
    }
    public UUID approve(UUID actor, UUID request, UUID membership, UUID contact, String message, String reviewHash,
        String evidence) {
        String verification = CommunityRules.text(evidence, 500);
        String requestHash = CommunityHash.of(actor, membership, contact, message, reviewHash, verification);
        return tx.execute(() -> {
            Membership before = reader.membership(membership).orElseThrow(CommunityException::missing);
            people.lock(before.personId()).orElseThrow(CommunityException::missing);
            repository.lockCommunity(before.communityId()).orElseThrow(CommunityException::missing);
            access.require(before.communityId());
            Optional<CommunityReader.Notice> old = reader.notice(request);
            if (old.isPresent()) {
                if (!old.orElseThrow().requestHash().equals(requestHash)) {
                    throw CommunityException.conflict("This notice request identifier was already used.");
                }
                return old.orElseThrow().deliveryId();
            }
            Review review = review(membership, contact, message);
            if (!review.hash().equals(reviewHash)) {
                throw CommunityException
                    .conflict("The member, community, contact or template changed. Review the notice again.");
            }
            UUID id = UUID.randomUUID();
            writer.notice(new CommunityReader.Notice(request, before.communityId(), membership, contact, requestHash,
                reviewHash, actor, id), verification, clock.instant());
            deliveries.queue("communities",
                new DeliverySnapshot(id, request, null, contact, review.recipient(), review.subject(), review.html(),
                    reviewHash, actor, clock.instant(), clock.instant(), "QUEUED", 0, "<" + id + "@onechurch.delivery>",
                    null, null, null, "", false, 0, "text/html", "communities.notice", review.templateVersion()));
            writer.audit(before.communityId(), request, "NOTICE_APPROVED", "Verified member notice queued.", actor,
                clock.instant());
            return id;
        });
    }
}
