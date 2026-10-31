package org.onechurch.communications.application.command.setmynotificationread;
import java.util.UUID;
public record SetMyNotificationReadCommand(UUID notificationId, boolean read) {}
