package org.onechurch.childrenministry.participation.presentation;
import org.onechurch.childrenministry.participation.application.command.assignclass.AssignClassCommand;
import org.onechurch.childrenministry.participation.application.command.assignclass.AssignClassHandler;
import org.onechurch.childrenministry.participation.application.command.createclass.CreateClassCommand;
import org.onechurch.childrenministry.participation.application.command.createclass.CreateClassHandler;
import org.onechurch.childrenministry.participation.application.command.createprogramme.CreateProgrammeCommand;
import org.onechurch.childrenministry.participation.application.command.createprogramme.CreateProgrammeHandler;
import org.onechurch.childrenministry.participation.application.command.endclassassignment.EndClassAssignmentCommand;
import org.onechurch.childrenministry.participation.application.command.endclassassignment.EndClassAssignmentHandler;
import org.onechurch.childrenministry.participation.application.command.endenrolment.EndEnrolmentCommand;
import org.onechurch.childrenministry.participation.application.command.endenrolment.EndEnrolmentHandler;
import org.onechurch.childrenministry.participation.application.command.enrolchild.EnrolChildCommand;
import org.onechurch.childrenministry.participation.application.command.enrolchild.EnrolChildHandler;
import org.onechurch.childrenministry.participation.application.query.getprogramme.GetProgrammeHandler;
import org.onechurch.childrenministry.participation.application.query.getprogramme.GetProgrammeQuery;
import org.onechurch.childrenministry.participation.application.query.listclassassignments.ListClassAssignmentsHandler;
import org.onechurch.childrenministry.participation.application.query.listclassassignments.ListClassAssignmentsQuery;
import org.onechurch.childrenministry.participation.application.query.listclasses.ListClassesHandler;
import org.onechurch.childrenministry.participation.application.query.listclasses.ListClassesQuery;
import org.onechurch.childrenministry.participation.application.query.listenrolments.ListEnrolmentsHandler;
import org.onechurch.childrenministry.participation.application.query.listenrolments.ListEnrolmentsQuery;
import org.onechurch.childrenministry.participation.application.query.listprogrammes.ListProgrammesHandler;
import org.onechurch.childrenministry.participation.application.query.listprogrammes.ListProgrammesQuery;
import org.onechurch.childrenministry.participation.domain.ClassAssignment;
import org.onechurch.childrenministry.participation.domain.Enrolment;
import org.onechurch.childrenministry.participation.domain.MinistryClass;
import org.onechurch.childrenministry.participation.domain.Programme;
import org.onechurch.childrenministry.participation.presentation.api.ParticipationApi;
import org.onechurch.childrenministry.safeguarding.application.command.publishconsentpolicy.PublishConsentPolicyCommand;
import org.onechurch.childrenministry.safeguarding.application.command.publishconsentpolicy.PublishConsentPolicyHandler;
import org.onechurch.childrenministry.safeguarding.application.query.getconsentpolicy.GetConsentPolicyHandler;
import org.onechurch.childrenministry.safeguarding.application.query.getconsentpolicy.GetConsentPolicyQuery;
import org.onechurch.childrenministry.safeguarding.domain.ConsentPolicy;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.List;
import java.util.UUID;
@RestController
public class ParticipationController implements ParticipationApi {
    private final CreateProgrammeHandler createProgramme;
    private final PublishConsentPolicyHandler publishConsentPolicy;
    private final CreateClassHandler createClass;
    private final EnrolChildHandler enrolChild;
    private final AssignClassHandler assignClass;
    private final EndEnrolmentHandler endEnrolment;
    private final EndClassAssignmentHandler endClassAssignment;
    private final ListProgrammesHandler listProgrammes;
    private final GetProgrammeHandler getProgramme;
    private final ListClassesHandler listClasses;
    private final ListEnrolmentsHandler listEnrolments;
    private final ListClassAssignmentsHandler listClassAssignments;
    private final GetConsentPolicyHandler getConsentPolicy;
    public ParticipationController(CreateProgrammeHandler createProgramme,
        PublishConsentPolicyHandler publishConsentPolicy, CreateClassHandler createClass, EnrolChildHandler enrolChild,
        AssignClassHandler assignClass, EndEnrolmentHandler endEnrolment, EndClassAssignmentHandler endClassAssignment,
        ListProgrammesHandler listProgrammes, GetProgrammeHandler getProgramme, ListClassesHandler listClasses,
        ListEnrolmentsHandler listEnrolments, ListClassAssignmentsHandler listClassAssignments,
        GetConsentPolicyHandler getConsentPolicy) {
        this.createProgramme = createProgramme;
        this.publishConsentPolicy = publishConsentPolicy;
        this.createClass = createClass;
        this.enrolChild = enrolChild;
        this.assignClass = assignClass;
        this.endEnrolment = endEnrolment;
        this.endClassAssignment = endClassAssignment;
        this.listProgrammes = listProgrammes;
        this.getProgramme = getProgramme;
        this.listClasses = listClasses;
        this.listEnrolments = listEnrolments;
        this.listClassAssignments = listClassAssignments;
        this.getConsentPolicy = getConsentPolicy;
    }
    @Override
    @PreAuthorize("hasAuthority('children.manage')")
    public Created createProgramme(CreateProgrammeRequest body, OAuth2AuthenticatedPrincipal principal) {
        return new Created(createProgramme.handle(new CreateProgrammeCommand(UUID.fromString(principal.getName()),
            body.name(), body.minStaff(), body.childrenPerStaff())));
    }
    @Override
    @PreAuthorize("hasAuthority('children.manage')")
    public Created publishConsentPolicy(PublishConsentPolicyRequest body, OAuth2AuthenticatedPrincipal principal) {
        return new Created(publishConsentPolicy
            .handle(new PublishConsentPolicyCommand(UUID.fromString(principal.getName()), body.programmeId(),
                body.expectedVersion(), body.label(), body.documentReference(), body.contentHash())));
    }
    @Override
    @PreAuthorize("hasAuthority('children.manage')")
    public Created createClass(CreateClassRequest body, OAuth2AuthenticatedPrincipal principal) {
        return new Created(createClass.handle(new CreateClassCommand(UUID.fromString(principal.getName()),
            body.programmeId(), body.name(), body.minAgeMonths(), body.maxAgeMonths())));
    }
    @Override
    @PreAuthorize("hasAuthority('children.manage')")
    public Created enrolChild(EnrolChildRequest body, OAuth2AuthenticatedPrincipal principal) {
        return new Created(enrolChild.handle(new EnrolChildCommand(UUID.fromString(principal.getName()),
            body.programmeId(), body.childId(), body.validFrom(), body.validUntil())));
    }
    @Override
    @PreAuthorize("hasAuthority('children.manage')")
    public Created assignClass(AssignClassRequest body, OAuth2AuthenticatedPrincipal principal) {
        return new Created(assignClass.handle(new AssignClassCommand(UUID.fromString(principal.getName()),
            body.enrolmentId(), body.classId(), body.validFrom(), body.validUntil())));
    }
    @Override
    @PreAuthorize("hasAuthority('children.manage')")
    public void endEnrolment(EndEnrolmentRequest body, OAuth2AuthenticatedPrincipal principal) {
        endEnrolment.handle(
            new EndEnrolmentCommand(UUID.fromString(principal.getName()), body.enrolmentId(), body.expectedVersion()));
    }
    @Override
    @PreAuthorize("hasAuthority('children.manage')")
    public void endClassAssignment(EndClassAssignmentRequest body, OAuth2AuthenticatedPrincipal principal) {
        endClassAssignment.handle(new EndClassAssignmentCommand(UUID.fromString(principal.getName()),
            body.assignmentId(), body.expectedVersion()));
    }
    @Override
    @PreAuthorize("hasAuthority('children.read')")
    public List<Programme> listProgrammes(String search, int limit, int offset) {
        return listProgrammes.handle(new ListProgrammesQuery(search, limit, offset));
    }
    @Override
    @PreAuthorize("hasAuthority('children.read')")
    public Programme getProgramme(UUID programmeId) {
        return getProgramme.handle(new GetProgrammeQuery(programmeId));
    }
    @Override
    @PreAuthorize("hasAuthority('children.read')")
    public List<MinistryClass> listClasses(UUID programmeId) {
        return listClasses.handle(new ListClassesQuery(programmeId));
    }
    @Override
    @PreAuthorize("hasAuthority('children.read')")
    public List<Enrolment> listEnrolments(UUID childId, int limit, int offset) {
        return listEnrolments.handle(new ListEnrolmentsQuery(childId, limit, offset));
    }
    @Override
    @PreAuthorize("hasAuthority('children.read')")
    public List<ClassAssignment> listClassAssignments(UUID childId, int limit, int offset) {
        return listClassAssignments.handle(new ListClassAssignmentsQuery(childId, limit, offset));
    }
    @Override
    @PreAuthorize("hasAuthority('children.read')")
    public ConsentPolicy getConsentPolicy(UUID policyId) {
        return getConsentPolicy.handle(new GetConsentPolicyQuery(policyId));
    }
}
