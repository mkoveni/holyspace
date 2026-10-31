package org.onechurch.childrenministry.participation.application.contract;

import org.onechurch.childrenministry.participation.domain.ClassAssignment;
import org.onechurch.childrenministry.participation.domain.Enrolment;
import org.onechurch.childrenministry.participation.domain.MinistryClass;
import org.onechurch.childrenministry.participation.domain.Programme;
import org.onechurch.childrenministry.safeguarding.domain.ConsentPolicy;

import java.time.LocalDate;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface ParticipationReader {
    List<ClassAssignment> expectedChildren(UUID classId, LocalDate on, int limit, int offset);
    Optional<Programme> programme(UUID id);
    Optional<ConsentPolicy> consentPolicy(UUID id);
    Optional<MinistryClass> ministryClass(UUID id);
    Optional<Enrolment> enrolment(UUID id);
    Optional<ClassAssignment> classAssignment(UUID id);
    List<Programme> programmes(String search, int limit, int offset);
    List<MinistryClass> classes(UUID programmeId);
    List<Enrolment> enrolments(UUID childId, int limit, int offset);
    List<ClassAssignment> assignments(UUID childId, int limit, int offset);
    Optional<Enrolment> enrolmentAt(UUID programmeId, UUID childId, LocalDate on);
    Optional<ClassAssignment> assignmentAt(UUID classId, UUID childId, LocalDate on);
}
