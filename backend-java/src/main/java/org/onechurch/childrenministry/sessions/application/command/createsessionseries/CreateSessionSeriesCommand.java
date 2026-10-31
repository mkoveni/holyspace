package org.onechurch.childrenministry.sessions.application.command.createsessionseries;
import org.onechurch.childrenministry.sessions.application.SessionSeriesDraft;

import java.util.UUID;
public record CreateSessionSeriesCommand(UUID actorId, UUID requestId, SessionSeriesDraft draft) {}
