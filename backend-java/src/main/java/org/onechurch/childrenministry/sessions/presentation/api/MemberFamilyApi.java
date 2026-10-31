package org.onechurch.childrenministry.sessions.presentation.api;
import org.onechurch.childrenministry.sessions.application.LabelIssue;
import org.onechurch.childrenministry.sessions.application.MemberFamilyViews;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestParam;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.media.Schema;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.AssertTrue;
import jakarta.validation.constraints.Min;

import java.util.UUID;
@Tag(name = "My children and family ministry")
public interface MemberFamilyApi {
    @Schema(name = "MemberChildPickupLabelRequest")
    record LabelRequest(@Min(0) long expectedVersion, @AssertTrue boolean replacePrevious) {}
    @GetMapping("/api/member/children")
    @Operation(summary = "List my authorized children", description = "Live verified member identity plus current evidenced guardian authority. Household and People relationships do not confer access. Read-only; no staff notes or collector identities.")
    MemberFamilyViews.FamilyPage<MemberFamilyViews.FamilyChild> children(@RequestParam(defaultValue = "25") int limit,
        @RequestParam(defaultValue = "0") int offset);
    @GetMapping("/api/member/children/{childId}")
    @Operation(summary = "Read my authorized child")
    MemberFamilyViews.FamilyChild child(@PathVariable UUID childId);
    @GetMapping("/api/member/children/{childId}/programmes")
    @Operation(summary = "Read my child’s enrolments and readiness today", description = "Current/future enrolments. Readiness is a preparation snapshot, not session admission or a capacity reservation.")
    MemberFamilyViews.FamilyPage<MemberFamilyViews.FamilyProgramme> programmes(@PathVariable UUID childId,
        @RequestParam(defaultValue = "10") int limit, @RequestParam(defaultValue = "0") int offset);
    @GetMapping("/api/member/children/{childId}/sessions")
    @Operation(summary = "Read my child’s upcoming sessions", description = "Next 90 days, using dated enrolment/class placement at the session’s location date. Staff always record arrival and verify live admission conditions.")
    MemberFamilyViews.FamilyPage<MemberFamilyViews.FamilySession> sessions(@PathVariable UUID childId,
        @RequestParam(defaultValue = "10") int limit, @RequestParam(defaultValue = "0") int offset);
    @GetMapping("/api/member/children/{childId}/attendance")
    @Operation(summary = "Read my child’s attendance", description = "History from the start of current verified authority. No support notes, incident reports, other collectors or audit reasons.")
    MemberFamilyViews.FamilyPage<MemberFamilyViews.FamilyVisit> attendance(@PathVariable UUID childId,
        @RequestParam(defaultValue = "10") int limit, @RequestParam(defaultValue = "0") int offset);
    @PostMapping("/api/member/children/{childId}/attendance/{attendanceId}/pickup-label")
    @Operation(summary = "Replace my child’s collection label", description = "Requires current guardian authority and own pickup grant. Explicit replacement invalidates every previous label. Five-minute hashed label, versioned under child/session/attendance locks. Staff must verify identity and live pickup rights; QR alone is not authority. No automatic retries.")
    LabelIssue label(@PathVariable UUID childId, @PathVariable UUID attendanceId,
        @Valid @RequestBody LabelRequest body);
}
