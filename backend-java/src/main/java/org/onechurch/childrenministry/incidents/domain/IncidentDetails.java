package org.onechurch.childrenministry.incidents.domain;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
public record IncidentDetails(String description, String immediateAction) {
    public IncidentDetails {
        description = ChildrenRules.text(description, 5000);
        immediateAction = ChildrenRules.text(immediateAction, 3000);
    }
    @Override
    public String toString() {
        return "IncidentDetails[restricted]";
    }
}
