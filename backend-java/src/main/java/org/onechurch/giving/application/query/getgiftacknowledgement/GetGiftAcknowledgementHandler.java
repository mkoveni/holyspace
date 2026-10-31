package org.onechurch.giving.application.query.getgiftacknowledgement;
import org.onechurch.giving.application.GivingAccess;
import org.onechurch.giving.application.contract.AcknowledgementReader;
public final class GetGiftAcknowledgementHandler {
    private final GivingAccess access;
    private final AcknowledgementReader reader;
    public GetGiftAcknowledgementHandler(GivingAccess access, AcknowledgementReader reader) {
        this.access = access;
        this.reader = reader;
    }
    public AcknowledgementReader.Status handle(GetGiftAcknowledgementQuery q) {
        access.read();
        return new AcknowledgementReader.Status(reader.findForGift(q.giftId()).orElse(null));
    }
}
