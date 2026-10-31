package org.onechurch.people.infrastructure.persistence;

import static org.onechurch.shared.infrastructure.jooq.Tables.PEOPLE_TITLES;

import org.jooq.DSLContext;
import org.onechurch.people.application.contract.TitleReader;
import org.onechurch.people.application.contract.TitleWriter;
import org.springframework.stereotype.Repository;

import java.util.List;

@Repository
public class JooqTitleStore implements TitleReader, TitleWriter {

    private final DSLContext db;

    public JooqTitleStore(DSLContext db) {
        this.db = db;
    }

    @Override
    public List<Title> titles() {
        return db.selectFrom(PEOPLE_TITLES)
            .orderBy(PEOPLE_TITLES.LABEL)
            .fetch(row -> new Title(row.getCode(), row.getLabel()));
    }

    @Override
    public boolean exists(String code) {
        return db.fetchExists(PEOPLE_TITLES, PEOPLE_TITLES.CODE.eq(code));
    }

    @Override
    public void add(String code, String label) {
        db.insertInto(PEOPLE_TITLES).set(PEOPLE_TITLES.CODE, code).set(PEOPLE_TITLES.LABEL, label).execute();
    }
}
