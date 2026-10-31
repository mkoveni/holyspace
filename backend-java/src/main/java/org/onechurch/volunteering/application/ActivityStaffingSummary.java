package org.onechurch.volunteering.application;
import org.onechurch.volunteering.application.contract.ActivityStaffing;
import org.onechurch.volunteering.application.contract.RosterReader;
import org.onechurch.volunteering.domain.Assignment;
import org.onechurch.volunteering.domain.Shift;

import java.util.List;
import java.util.UUID;
public final class ActivityStaffingSummary implements ActivityStaffing {
    private final RosterReader roster;
    private final VolunteerReadiness readiness;
    public ActivityStaffingSummary(RosterReader roster, VolunteerReadiness readiness) {
        this.roster = roster;
        this.readiness = readiness;
    }
    public Summary summarize(String kind, UUID source) {
        List<Shift> all = roster.sourceShifts(kind, source, 201);
        int shifts = 0, positions = 0, confirmed = 0, ready = 0, proposed = 0, gaps = 0;
        for (Shift shift : all.stream().limit(200).filter(s -> s.status().equals("OPEN")).toList()) {
            shifts++;
            positions += shift.positions();
            List<Assignment> assignments = roster.assignments(shift.id());
            int qualified = 0;
            for (Assignment assignment : assignments) {
                if (assignment.status().equals("PROPOSED")) {
                    proposed++;
                }
                if (assignment.status().equals("CONFIRMED")) {
                    confirmed++;
                    if (readiness.assess(assignment.volunteerId(), shift, assignment.id()).ready()) {
                        qualified++;
                    }
                }
            }
            ready += qualified;
            gaps += Math.max(0, shift.positions() - qualified);
        }
        return new Summary(shifts, positions, confirmed, ready, proposed, gaps, all.size() <= 200);
    }
}
