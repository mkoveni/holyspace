package org.onechurch.people.application.command.updatemyprofile;
import org.onechurch.people.domain.Gender;

import java.util.UUID;
public record UpdateMyProfileCommand(UUID actorId, String titleCode, String firstName, String lastName, Gender gender,
    long expectedVersion) {}
