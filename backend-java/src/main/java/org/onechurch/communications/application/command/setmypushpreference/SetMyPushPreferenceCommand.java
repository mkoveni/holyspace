package org.onechurch.communications.application.command.setmypushpreference;
public record SetMyPushPreferenceCommand(String topic, boolean enabled, long expectedVersion) {}
