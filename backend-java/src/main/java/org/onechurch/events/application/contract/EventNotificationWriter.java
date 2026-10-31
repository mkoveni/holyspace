package org.onechurch.events.application.contract;
public interface EventNotificationWriter {
    void insert(EventNotificationReader.Notification notification);
}
