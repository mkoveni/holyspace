package org.onechurch.giving.application.query.getacknowledgement;
import org.onechurch.giving.application.GivingAccess;
import org.onechurch.giving.application.contract.AcknowledgementReader;
import org.onechurch.giving.domain.GivingException;
public final class GetAcknowledgementHandler {
    private final GivingAccess access;
    private final AcknowledgementReader reader;
    public GetAcknowledgementHandler(GivingAccess access, AcknowledgementReader reader) {
        this.access = access;
        this.reader = reader;
    }
    public AcknowledgementReader.View handle(GetAcknowledgementQuery q) {
        access.read();
        return reader.get(q.acknowledgementId()).orElseThrow(GivingException::missing);
    }
}
