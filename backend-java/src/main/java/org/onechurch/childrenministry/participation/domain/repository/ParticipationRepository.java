package org.onechurch.childrenministry.participation.domain.repository;

import org.onechurch.childrenministry.participation.domain.ClassAssignment;
import org.onechurch.childrenministry.participation.domain.Enrolment;
import org.onechurch.childrenministry.participation.domain.MinistryClass;
import org.onechurch.childrenministry.participation.domain.Programme;
import org.onechurch.childrenministry.safeguarding.domain.ConsentPolicy;

import java.time.Instant;
import java.util.Optional;
import java.util.UUID;
public interface ParticipationRepository {
    Optional<Programme> programmeForUpdate(UUID id);
    void insertProgramme(Programme value);
    void updateProgramme(Programme value);
    Optional<ConsentPolicy> consentPolicyForUpdate(UUID id);
    void insertConsentPolicy(ConsentPolicy value);
    Optional<MinistryClass> ministryClassForUpdate(UUID id);
    void insertMinistryClass(MinistryClass value);
    Optional<Enrolment> enrolmentForUpdate(UUID id);
    void insertEnrolment(Enrolment value);
    void updateEnrolment(Enrolment value);
    void endAssignments(UUID enrolmentId, Instant at);
    Optional<ClassAssignment> classAssignmentForUpdate(UUID id);
    void insertClassAssignment(ClassAssignment value);
    void updateClassAssignment(ClassAssignment value);

}
