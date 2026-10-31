package org.onechurch.communications.domain;
public enum MemberNotice {
    VOLUNTEER_INVITATION("VOLUNTEERING", "You’re invited to serve",
        "An invitation is ready for your response. Open My volunteering to review the current details.",
        "VOLUNTEERING"), DUTY_CHANGED("VOLUNTEERING", "Your serving plans have changed",
            "Please review your latest duty details and commitment in My volunteering.",
            "VOLUNTEERING"), DUTY_ASSIGNED("VOLUNTEERING", "A duty has been added",
                "Your serving plans have been updated. Open My volunteering for details and readiness.",
                "VOLUNTEERING"), EVENT_BOOKING_UPDATED("EVENTS", "Your event booking was updated",
                    "Open My events to see your current booking status. This notice is not an admission pass.",
                    "EVENTS");
    private final String topic;
    private final String title;
    private final String message;
    private final String destination;
    MemberNotice(String topic, String title, String message, String destination) {
        this.topic = topic;
        this.title = title;
        this.message = message;
        this.destination = destination;
    }
    public String topic() {
        return topic;
    }
    public String title() {
        return title;
    }
    public String message() {
        return message;
    }
    public String destination() {
        return destination;
    }
}
