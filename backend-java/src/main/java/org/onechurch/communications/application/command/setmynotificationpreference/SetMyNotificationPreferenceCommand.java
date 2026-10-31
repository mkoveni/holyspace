package org.onechurch.communications.application.command.setmynotificationpreference;
public record SetMyNotificationPreferenceCommand(String topic, boolean enabled, long expectedVersion) {}
