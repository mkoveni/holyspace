package org.onechurch.childrenministry.sessions.infrastructure;

import org.onechurch.childrenministry.sessions.application.command.importservingstaff.ImportServingStaffHandler;
import org.onechurch.childrenministry.sessions.application.command.setsessionstaff.SetSessionStaffHandler;
import org.onechurch.childrenministry.sessions.application.contract.SessionReader;
import org.onechurch.childrenministry.sessions.application.contract.VolunteerStaffLinkWriter;
import org.onechurch.childrenministry.sessions.application.query.listservingcandidates.ListServingCandidatesHandler;
import org.onechurch.childrenministry.sessions.domain.repository.SessionRepository;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.application.UnitOfWork;
import org.onechurch.volunteering.application.contract.StaffingDirectory;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

@Configuration
public class ServingStaffConfiguration {
    @Bean
    ListServingCandidatesHandler listServingCandidates(PermissionChecker auth, SessionReader sessions,
        StaffingDirectory staffing) {
        return new ListServingCandidatesHandler(auth, sessions, staffing);
    }
    @Bean
    ImportServingStaffHandler importServingStaff(PermissionChecker auth, PersonDirectory people, SessionReader sessions,
        SessionRepository repository, StaffingDirectory staffing, VolunteerStaffLinkWriter links,
        SetSessionStaffHandler staff, UnitOfWork tx) {
        return new ImportServingStaffHandler(auth, people, sessions, repository, staffing, links, staff, tx);
    }
}
