package org.onechurch.giving.application.query.getgift;
import org.onechurch.giving.application.GivingAccess;
import org.onechurch.giving.application.contract.GivingReader;
import org.onechurch.giving.domain.GivingException;
public final class GetGiftHandler {
    private final GivingAccess access;
    private final GivingReader reader;
    public GetGiftHandler(GivingAccess access, GivingReader reader) {
        this.access = access;
        this.reader = reader;
    }
    public GivingReader.Detail handle(GetGiftQuery q) {
        access.read();
        return reader.detail(q.giftId()).orElseThrow(GivingException::missing);
    }
}
