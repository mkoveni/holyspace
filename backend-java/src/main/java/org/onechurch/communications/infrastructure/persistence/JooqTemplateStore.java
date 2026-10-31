package org.onechurch.communications.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNICATION_TEMPLATES;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNICATION_TEMPLATE_REVISIONS;

import org.jooq.Condition;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;
import org.onechurch.communications.application.contract.TemplateReader;
import org.onechurch.communications.domain.CommunicationException;
import org.onechurch.communications.domain.EmailTemplate;
import org.onechurch.communications.domain.TemplateContent;
import org.onechurch.communications.domain.repository.TemplateRepository;
import org.onechurch.shared.infrastructure.jooq.tables.records.CommunicationTemplateRevisionsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.CommunicationTemplatesRecord;
import org.springframework.dao.DuplicateKeyException;
import org.springframework.stereotype.Repository;

import java.util.Arrays;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
@Repository
public class JooqTemplateStore implements TemplateReader, TemplateRepository {
    private final DSLContext db;
    public JooqTemplateStore(DSLContext db) {
        this.db = db;
    }
    private List<String> variables(String value) {
        return value.isBlank() ? List.of() : Arrays.asList(value.split(","));
    }
    private View view(CommunicationTemplatesRecord r) {
        return new View(r.getId(), r.getTemplateKey(), r.getName(), r.getSubject(), r.getHtml(),
            variables(r.getVariables()), r.getStatus(), r.getVersion(), r.getPublishedVersion(),
            CommunicationRecords.instant(r.getUpdatedAt()));
    }
    private EmailTemplate aggregate(CommunicationTemplatesRecord r) {
        return new EmailTemplate(r.getId(), r.getTemplateKey(), r.getName(),
            new TemplateContent(r.getSubject(), r.getHtml(), variables(r.getVariables())), r.getStatus(),
            r.getVersion(), r.getUpdatedBy(), CommunicationRecords.instant(r.getUpdatedAt()), r.getPublishedVersion());
    }
    private Revision revision(CommunicationTemplateRevisionsRecord r) {
        return new Revision(r.getVersion(), r.getSubject(), r.getHtml(), variables(r.getVariables()),
            CommunicationRecords.instant(r.getPublishedAt()));
    }
    public Optional<View> find(UUID id) {
        return db.selectFrom(COMMUNICATION_TEMPLATES)
            .where(COMMUNICATION_TEMPLATES.ID.eq(id))
            .fetchOptional(this::view);
    }
    public Page list(String search, int limit, int offset) {
        String pattern = "%" + search.replace("!", "!!").replace("%", "!%").replace("_", "!_") + "%";
        Condition condition =
            search.isBlank() ? DSL.noCondition() : COMMUNICATION_TEMPLATES.NAME.likeIgnoreCase(pattern, '!')
                .or(COMMUNICATION_TEMPLATES.TEMPLATE_KEY.likeIgnoreCase(pattern, '!'));
        return new Page(db.selectFrom(COMMUNICATION_TEMPLATES)
            .where(condition)
            .orderBy(COMMUNICATION_TEMPLATES.UPDATED_AT.desc(), COMMUNICATION_TEMPLATES.ID)
            .limit(limit)
            .offset(offset)
            .fetch(this::view), db.fetchCount(COMMUNICATION_TEMPLATES, condition));
    }
    public List<Revision> revisions(UUID id, int limit, int offset) {
        return db.selectFrom(COMMUNICATION_TEMPLATE_REVISIONS)
            .where(COMMUNICATION_TEMPLATE_REVISIONS.TEMPLATE_ID.eq(id))
            .orderBy(COMMUNICATION_TEMPLATE_REVISIONS.VERSION.desc())
            .limit(limit)
            .offset(offset)
            .fetch(this::revision);
    }
    public Optional<Revision> published(String key) {
        return db.select(COMMUNICATION_TEMPLATE_REVISIONS.fields())
            .from(COMMUNICATION_TEMPLATE_REVISIONS)
            .join(COMMUNICATION_TEMPLATES)
            .on(COMMUNICATION_TEMPLATES.ID.eq(COMMUNICATION_TEMPLATE_REVISIONS.TEMPLATE_ID))
            .where(COMMUNICATION_TEMPLATES.TEMPLATE_KEY.eq(key))
            .and(COMMUNICATION_TEMPLATES.STATUS.ne("ARCHIVED"))
            .and(COMMUNICATION_TEMPLATE_REVISIONS.VERSION.eq(COMMUNICATION_TEMPLATES.PUBLISHED_VERSION))
            .fetchOptional(row -> revision(row.into(COMMUNICATION_TEMPLATE_REVISIONS)));
    }
    public Optional<EmailTemplate> lock(UUID id) {
        return db.selectFrom(COMMUNICATION_TEMPLATES)
            .where(COMMUNICATION_TEMPLATES.ID.eq(id))
            .forUpdate()
            .fetchOptional(this::aggregate);
    }
    public void insert(EmailTemplate t) {
        try {
            db.insertInto(COMMUNICATION_TEMPLATES)
                .set(COMMUNICATION_TEMPLATES.ID, t.id())
                .set(COMMUNICATION_TEMPLATES.TEMPLATE_KEY, t.key())
                .set(COMMUNICATION_TEMPLATES.NAME, t.name())
                .set(COMMUNICATION_TEMPLATES.SUBJECT, t.content().subject())
                .set(COMMUNICATION_TEMPLATES.HTML, t.content().html())
                .set(COMMUNICATION_TEMPLATES.VARIABLES, String.join(",", t.content().variables()))
                .set(COMMUNICATION_TEMPLATES.STATUS, t.status())
                .set(COMMUNICATION_TEMPLATES.VERSION, t.version())
                .set(COMMUNICATION_TEMPLATES.UPDATED_BY, t.updatedBy())
                .set(COMMUNICATION_TEMPLATES.UPDATED_AT, CommunicationRecords.time(t.updatedAt()))
                .execute();
        } catch (DuplicateKeyException duplicate) {
            throw CommunicationException.conflict("A template with this key already exists.");
        }
    }
    public void save(EmailTemplate t) {
        db.update(COMMUNICATION_TEMPLATES)
            .set(COMMUNICATION_TEMPLATES.NAME, t.name())
            .set(COMMUNICATION_TEMPLATES.SUBJECT, t.content().subject())
            .set(COMMUNICATION_TEMPLATES.HTML, t.content().html())
            .set(COMMUNICATION_TEMPLATES.VARIABLES, String.join(",", t.content().variables()))
            .set(COMMUNICATION_TEMPLATES.STATUS, t.status())
            .set(COMMUNICATION_TEMPLATES.VERSION, t.version())
            .set(COMMUNICATION_TEMPLATES.UPDATED_BY, t.updatedBy())
            .set(COMMUNICATION_TEMPLATES.UPDATED_AT, CommunicationRecords.time(t.updatedAt()))
            .set(COMMUNICATION_TEMPLATES.PUBLISHED_VERSION, t.publishedVersion())
            .where(COMMUNICATION_TEMPLATES.ID.eq(t.id()))
            .execute();
    }
    public void publish(EmailTemplate t) {
        db.insertInto(COMMUNICATION_TEMPLATE_REVISIONS)
            .set(COMMUNICATION_TEMPLATE_REVISIONS.TEMPLATE_ID, t.id())
            .set(COMMUNICATION_TEMPLATE_REVISIONS.VERSION, t.version())
            .set(COMMUNICATION_TEMPLATE_REVISIONS.SUBJECT, t.content().subject())
            .set(COMMUNICATION_TEMPLATE_REVISIONS.HTML, t.content().html())
            .set(COMMUNICATION_TEMPLATE_REVISIONS.VARIABLES, String.join(",", t.content().variables()))
            .set(COMMUNICATION_TEMPLATE_REVISIONS.PUBLISHED_BY, t.updatedBy())
            .set(COMMUNICATION_TEMPLATE_REVISIONS.PUBLISHED_AT, CommunicationRecords.time(t.updatedAt()))
            .execute();
    }
}
