package org.onechurch.people.application.command.endaddress;

import java.util.UUID;

public record EndAddressCommand(UUID actorId, UUID personId, long expectedVersion, UUID addressId) {}
