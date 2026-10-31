package org.onechurch.volunteering.application.query.listvolunteers;

public record ListVolunteersQuery(String search, int limit, int offset) {}
