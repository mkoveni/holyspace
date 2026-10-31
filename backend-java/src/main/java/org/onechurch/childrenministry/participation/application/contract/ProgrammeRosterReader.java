package org.onechurch.childrenministry.participation.application.contract;
import org.onechurch.childrenministry.participation.application.query.listprogrammechildren.ProgrammeChildrenPage.Placement;
import org.onechurch.childrenministry.participation.domain.Enrolment;

import java.time.LocalDate;
import java.util.Collection;
import java.util.List;
import java.util.Map;
import java.util.UUID;
public interface ProgrammeRosterReader {
    record Page(List<Enrolment> enrolments, long total) {}
    Page list(UUID programmeId, Collection<UUID> childIds, String status, LocalDate on, int limit, int offset);
    Map<UUID, List<Placement>> placements(Collection<UUID> enrolmentIds, LocalDate on);
}
