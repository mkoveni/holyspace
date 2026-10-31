package org.onechurch.childrenministry.participation.application.command.createroom;

import java.util.UUID;
public record CreateRoomCommand(UUID actorId, UUID locationId, String name, int capacity) {}
