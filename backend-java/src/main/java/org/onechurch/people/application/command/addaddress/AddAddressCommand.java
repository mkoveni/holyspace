package org.onechurch.people.application.command.addaddress;

import java.util.UUID;

public record AddAddressCommand(UUID actorId, UUID personId, long expectedVersion, String line1, String line2,
    String city, String region, String postalCode, String countryCode) {}
