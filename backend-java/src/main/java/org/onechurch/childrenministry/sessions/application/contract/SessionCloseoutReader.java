package org.onechurch.childrenministry.sessions.application.contract;
import org.onechurch.childrenministry.sessions.application.query.getsessioncloseout.SessionCloseout;

import java.time.Instant;
import java.util.UUID;
public interface SessionCloseoutReader {
    SessionCloseout summarize(UUID sessionId, Instant now);
}
