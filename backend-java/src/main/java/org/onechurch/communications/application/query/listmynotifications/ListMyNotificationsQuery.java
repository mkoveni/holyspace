package org.onechurch.communications.application.query.listmynotifications;
public record ListMyNotificationsQuery(boolean unreadOnly, int limit, int offset) {}
