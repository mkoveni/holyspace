package org.onechurch.communications.application.command.revokemypushdevice;
import java.util.UUID;
public record RevokeMyPushDeviceCommand(UUID deviceId) {}
