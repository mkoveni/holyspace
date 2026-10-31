package org.onechurch.childrenministry.sessions.application.query.getsessioncloseout;
import org.onechurch.childrenministry.incidents.application.contract.IncidentReader;

import java.time.Instant;
public record SessionCloseout(long visits, long children, long awaitingCollection, long collected, long transferredOut,
    long labelsNeedingAttention, long failedHandoverAttempts, long supervisorCollections, Instant checkedAt,
    IncidentReader.Counts incidents) {
    public SessionCloseout(long visits, long children, long awaitingCollection, long collected, long transferredOut,
        long labelsNeedingAttention, long failedHandoverAttempts, long supervisorCollections, Instant checkedAt) {
        this(visits, children, awaitingCollection, collected, transferredOut, labelsNeedingAttention,
            failedHandoverAttempts, supervisorCollections, checkedAt, null);
    }
    public SessionCloseout withIncidents(IncidentReader.Counts value) {
        return new SessionCloseout(visits, children, awaitingCollection, collected, transferredOut,
            labelsNeedingAttention, failedHandoverAttempts, supervisorCollections, checkedAt, value);
    }
}
