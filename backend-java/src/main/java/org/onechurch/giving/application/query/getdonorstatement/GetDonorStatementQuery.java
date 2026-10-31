package org.onechurch.giving.application.query.getdonorstatement;
import java.time.LocalDate;
import java.util.UUID;
public record GetDonorStatementQuery(UUID donorId, LocalDate from, LocalDate to) {}
