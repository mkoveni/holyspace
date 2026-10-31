package org.onechurch.childrenministry.sessions.infrastructure.persistence;

import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_VOLUNTEER_STAFF_LINKS;

import org.jooq.DSLContext;
import org.onechurch.childrenministry.sessions.application.contract.VolunteerStaffLinkWriter;
import org.onechurch.childrenministry.sessions.application.contract.VolunteerStaffLinks;
import org.springframework.stereotype.Repository;

import java.util.Optional;
import java.util.UUID;

@Repository
public class JooqVolunteerStaffLinks implements VolunteerStaffLinks, VolunteerStaffLinkWriter {
    private final DSLContext db;
    public JooqVolunteerStaffLinks(DSLContext db) {
        this.db = db;
    }
    public Optional<Link> find(UUID session, UUID worker) {
        return db.selectFrom(CHILDREN_VOLUNTEER_STAFF_LINKS)
            .where(CHILDREN_VOLUNTEER_STAFF_LINKS.SESSION_ID.eq(session)
                .and(CHILDREN_VOLUNTEER_STAFF_LINKS.WORKER_ID.eq(worker)))
            .fetchOptional(r -> new Link(r.getAssignmentId(), r.getRole()));
    }
    public void save(UUID session, UUID worker, UUID assignment, String role) {
        db.insertInto(CHILDREN_VOLUNTEER_STAFF_LINKS)
            .set(CHILDREN_VOLUNTEER_STAFF_LINKS.SESSION_ID, session)
            .set(CHILDREN_VOLUNTEER_STAFF_LINKS.WORKER_ID, worker)
            .set(CHILDREN_VOLUNTEER_STAFF_LINKS.ASSIGNMENT_ID, assignment)
            .set(CHILDREN_VOLUNTEER_STAFF_LINKS.ROLE, role)
            .onConflict(CHILDREN_VOLUNTEER_STAFF_LINKS.SESSION_ID, CHILDREN_VOLUNTEER_STAFF_LINKS.WORKER_ID)
            .doUpdate()
            .set(CHILDREN_VOLUNTEER_STAFF_LINKS.ASSIGNMENT_ID, assignment)
            .set(CHILDREN_VOLUNTEER_STAFF_LINKS.ROLE, role)
            .execute();
    }
}
