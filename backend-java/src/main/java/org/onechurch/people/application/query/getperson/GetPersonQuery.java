package org.onechurch.people.application.query.getperson;

import java.time.LocalDate;
import java.util.UUID;

public record GetPersonQuery(UUID personId, LocalDate asOf) {}
