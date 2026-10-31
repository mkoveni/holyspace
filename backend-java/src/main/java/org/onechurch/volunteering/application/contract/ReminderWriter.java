package org.onechurch.volunteering.application.contract;

public interface ReminderWriter {
    void insert(ReminderReader.Reminder reminder);
}
