package org.onechurch.childrenministry.safeguarding.application;
import org.onechurch.childrenministry.safeguarding.application.contract.SafeguardingFollowUpReader;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.people.application.contract.PersonDirectory;

import java.time.Clock;
import java.time.Instant;
import java.time.LocalDate;
import java.time.ZoneId;
import java.util.ArrayList;
import java.util.Comparator;
import java.util.HashSet;
import java.util.List;
import java.util.Map;
import java.util.Set;
import java.util.UUID;
import java.util.function.Function;
import java.util.stream.Collectors;
import java.util.stream.Stream;
public final class SafeguardingFollowUp {
    private final SafeguardingFollowUpReader reader;
    private final PersonDirectory people;
    private final Clock clock;
    private final ZoneId zone;
    public SafeguardingFollowUp(SafeguardingFollowUpReader reader, PersonDirectory people, Clock clock, ZoneId zone) {
        this.reader = reader;
        this.people = people;
        this.clock = clock;
        this.zone = zone;
    }
    public SafeguardingFollowUpView list(UUID programmeId, String search, String kind, String state, int days,
        int limit, int offset, boolean includeCare) {
        ChildrenRules.page(limit, offset);
        if (days < 1 || days > 90 || kind == null || state == null
            || !List.of("ALL", "GUARDIAN", "CONSENT", "PICKUP", "CARE").contains(kind)
            || !List.of("ALL", "MISSING", "EXPIRED", "EXPIRING", "DUE").contains(state)) {
            throw ChildrenException.invalid("Choose a valid follow-up view and a 1–90 day horizon.");
        }
        Instant now = ChildrenRules.instant(clock.instant());
        LocalDate on = now.atZone(zone).toLocalDate();
        LocalDate through = on.plusDays(days);
        Instant threshold = through.plusDays(1).atStartOfDay(zone).toInstant();
        String term = ChildrenRules.optional(search, 120);
        SafeguardingFollowUpReader.Facts facts =
            reader.load(programmeId, term.isEmpty() ? null : people.matchingIds(term), on, now, includeCare);
        if (facts.children().size() > 1000) {
            throw ChildrenException
                .invalid("More than 1,000 active child/programme records match. Narrow the programme or child name.");
        }
        List<UUID> ids =
            Stream
                .concat(facts.children().stream().map(SafeguardingFollowUpReader.Child::childId),
                    facts.pickups().stream().map(SafeguardingFollowUpReader.Pickup::collectorId))
                .distinct()
                .toList();
        Map<UUID, PersonDirectory.Entry> names =
            people.getAll(ids).stream().collect(Collectors.toMap(PersonDirectory.Entry::id, Function.identity()));
        Map<UUID, LocalDate> care = facts.care()
            .stream()
            .collect(
                Collectors.toMap(SafeguardingFollowUpReader.Care::childId, SafeguardingFollowUpReader.Care::reviewOn));
        List<SafeguardingFollowUpView.Issue> issues = new ArrayList<>();
        Set<UUID> careAdded = new HashSet<>();
        for (SafeguardingFollowUpReader.Child child : facts.children()) {
            PersonDirectory.Entry identity = names.get(child.childId());
            if (identity != null && (!identity.available()
                || (identity.birthDate() != null && !identity.birthDate().plusYears(18).isAfter(on)))) {
                continue;
            }
            String name = identity == null ? "Unavailable person" : identity.name();
            add(issues, child, name, "GUARDIAN", child.guardianUntil(), now, threshold, "guardian",
                "Verified guardian authority");
            add(issues, child, name, "CONSENT", child.consentUntil(), now, threshold,
                child.policyId() == null ? "policy" : "consent", "Consent for the current programme policy");
            Instant pickupUntil = facts.pickups()
                .stream()
                .filter(
                    value -> value.childId().equals(child.childId()) && value.programmeId().equals(child.programmeId()))
                .filter(value -> {
                    PersonDirectory.Entry collector = names.get(value.collectorId());
                    return collector != null && collector.available() && collector.birthDate() != null
                        && !collector.birthDate().plusYears(18).isAfter(on);
                })
                .map(SafeguardingFollowUpReader.Pickup::until)
                .max(Instant::compareTo)
                .orElse(null);
            add(issues, child, name, "PICKUP", pickupUntil, now, threshold, "pickup",
                "Eligible pickup permission with valid guardian authority");
            LocalDate review = care.get(child.childId());
            if (includeCare && review != null && !review.isAfter(through) && careAdded.add(child.childId())) {
                issues.add(new SafeguardingFollowUpView.Issue("CARE:" + child.childId(), "CARE",
                    review.isAfter(on) ? "EXPIRING" : "DUE", child.childId(), name, null, "All programmes", null,
                    review, "care",
                    review.isAfter(on) ? "A care review is coming up." : "The current care record is due for review."));
            }
        }
        issues.sort(Comparator.comparingInt((SafeguardingFollowUpView.Issue issue) -> switch (issue.state()) {
            case "MISSING", "EXPIRED", "DUE" -> 0;
            default -> 1;
        })
            .thenComparing(issue -> issue.expiresAt() == null
                ? (issue.reviewOn() == null ? Instant.EPOCH : issue.reviewOn().atStartOfDay(zone).toInstant())
                : issue.expiresAt())
            .thenComparing(SafeguardingFollowUpView.Issue::id));
        SafeguardingFollowUpView.Summary summary = new SafeguardingFollowUpView.Summary(
            issues.stream().map(SafeguardingFollowUpView.Issue::childId).distinct().count(),
            issues.stream().filter(issue -> issue.state().equals("MISSING")).count(),
            issues.stream().filter(issue -> issue.state().equals("EXPIRED")).count(),
            issues.stream().filter(issue -> issue.state().equals("EXPIRING")).count(),
            issues.stream().filter(issue -> issue.kind().equals("CARE") && issue.state().equals("DUE")).count());
        List<SafeguardingFollowUpView.Issue> filtered = issues.stream()
            .filter(issue -> (kind.equals("ALL") || issue.kind().equals(kind))
                && (state.equals("ALL") || issue.state().equals(state)))
            .toList();
        return new SafeguardingFollowUpView(filtered.stream().skip(offset).limit(limit).toList(), filtered.size(),
            summary, includeCare, through, zone.getId(), now);
    }
    private void add(List<SafeguardingFollowUpView.Issue> issues, SafeguardingFollowUpReader.Child child, String name,
        String kind, Instant expiry, Instant now, Instant threshold, String action, String label) {
        String state = expiry == null ? "MISSING"
            : !expiry.isAfter(now) ? "EXPIRED" : expiry.isBefore(threshold) ? "EXPIRING" : "";
        if (state.isEmpty()) {
            return;
        }
        String message =
            action.equals("policy") ? "Publish a programme consent policy before recording current consent."
                : label + (state.equals("MISSING") ? " is missing or no longer usable."
                    : state.equals("EXPIRED") ? " has expired." : " expires within the follow-up window.");
        issues.add(new SafeguardingFollowUpView.Issue(kind + ":" + child.childId() + ":" + child.programmeId(), kind,
            state, child.childId(), name, child.programmeId(), child.programme(), expiry, null, action, message));
    }
}
