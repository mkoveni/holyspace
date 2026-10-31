package org.onechurch.giving.application.query.listgifts;
import org.onechurch.giving.application.GivingAccess;
import org.onechurch.giving.application.GivingFilters;
import org.onechurch.giving.application.contract.GivingReader;
public final class ListGiftsHandler {
    private final GivingAccess access;
    private final GivingReader reader;
    public ListGiftsHandler(GivingAccess access, GivingReader reader) {
        this.access = access;
        this.reader = reader;
    }
    public GivingReader.GiftPage handle(ListGiftsQuery q) {
        access.read();
        GivingFilters.validate(q.filter());
        return reader.gifts(q.filter());
    }
}
