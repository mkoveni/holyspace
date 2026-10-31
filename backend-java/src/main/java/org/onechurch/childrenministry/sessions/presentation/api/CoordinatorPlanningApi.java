package org.onechurch.childrenministry.sessions.presentation.api;
import org.onechurch.childrenministry.safeguarding.application.SafeguardingFollowUpView;
import org.onechurch.childrenministry.sessions.application.TeamPlanDraft;
import org.onechurch.childrenministry.sessions.application.TeamPlanningView;
import org.onechurch.childrenministry.sessions.domain.StaffRole;
import org.springframework.http.HttpStatus;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseStatus;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

import java.time.LocalDate;
import java.util.List;
import java.util.UUID;
@Tag(name = "Children Coordinator Planning")
public interface CoordinatorPlanningApi {
    record Target(@NotNull UUID sessionId, @Min(0) long version) {}
    record Draft(@NotNull @Size(min = 1, max = 25) List<@NotNull @Valid Target> targets, @NotNull UUID workerId,
        UUID replaceWorkerId, @NotNull StaffRole role) {
        public TeamPlanDraft toDraft() {
            return new TeamPlanDraft(
                targets.stream().map(target -> new TeamPlanDraft.Target(target.sessionId(), target.version())).toList(),
                workerId, replaceWorkerId, role);
        }
    }
    record Apply(@Valid @NotNull Draft draft, @NotBlank @Size(max = 500) String reason) {}
    @GetMapping("/api/children/team-planning")
    @Operation(summary = "Plan upcoming session teams", description = "Requires children.manage. Inclusive church-local dates, at most 90 days and 1,000 matching sessions. Expected capacity ignores presence and excludes unqualified or double-booked staff. Summary covers the date/programme/location/search scope before readiness filtering and pagination. Day-of admission still requires qualified present staff.")
    TeamPlanningView team(@RequestParam LocalDate from, @RequestParam LocalDate until,
        @RequestParam(required = false) UUID programmeId, @RequestParam(required = false) UUID locationId,
        @RequestParam(defaultValue = "") String search, @RequestParam(defaultValue = "ALL") String state,
        @RequestParam(defaultValue = "25") int limit, @RequestParam(defaultValue = "0") int offset);
    @GetMapping("/api/children/team-planning/workers")
    @Operation(summary = "Find registered planning workers", description = "Requires children.manage. Searches People identity through its published contract and returns names, clearance dates and revocation indicators without evidence or account identifiers.")
    TeamPlanningView.Workers workers(@RequestParam(defaultValue = "") String search,
        @RequestParam(defaultValue = "25") int limit, @RequestParam(defaultValue = "0") int offset);
    @PostMapping("/api/children/team-planning/preview")
    @Operation(summary = "Preview bulk team assignments", description = "Requires children.manage. Add or replace one registered worker across 1–25 versioned future PLANNED sessions. Shows projected capacity, warnings and assignment clashes. Confirmed presence cannot be changed in bulk. Preview writes nothing; clearance warnings allow planning, but clashes block saving.")
    TeamPlanningView.Preview preview(@Valid @RequestBody Draft body);
    @PostMapping("/api/children/team-planning/apply")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Apply a reviewed team plan", description = "Requires children.manage and audit reason. Locks sessions and workers in UUID order, then revalidates versions, future status, replacement assignments, presence and clashes. Applies all assignments atomically, marks only affected assignments absent, increments session versions and audits every session. No care, identity evidence, clearance records or attendance are modified.")
    void apply(@Valid @RequestBody Apply body, @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @GetMapping("/api/children/safeguarding-follow-up")
    @Operation(summary = "Review safeguarding follow-up", description = "Requires children.safeguarding. Covers active child/programme enrolments and up to 1,000 matching pairs; narrow programme or child search for larger cohorts. Horizon 1–90 days uses church dates. Calculates best effective guardian, current-policy consent and eligible collector coverage; alternatives prevent false expiry alerts. Optional current-care review dates require children.support; CARE filter without it is forbidden. Summary covers all issue types before type/state filtering and pagination. Reads no care payload, evidence, codes or collector identity into the response and performs no writes.")
    SafeguardingFollowUpView followUp(@RequestParam(required = false) UUID programmeId,
        @RequestParam(defaultValue = "") String search, @RequestParam(defaultValue = "ALL") String kind,
        @RequestParam(defaultValue = "ALL") String state, @RequestParam(defaultValue = "30") int days,
        @RequestParam(defaultValue = "25") int limit, @RequestParam(defaultValue = "0") int offset);
}
