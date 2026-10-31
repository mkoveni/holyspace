package org.onechurch.payments.application.query.getmygiving;
import org.onechurch.giving.application.contract.DonorIdentity;
import org.onechurch.giving.application.contract.GivingReader;
import org.onechurch.giving.application.contract.OnlineFundDirectory;
import org.onechurch.payments.application.contract.DonorPaymentReader;
import org.onechurch.payments.application.contract.PaymentGateway;
import org.onechurch.payments.domain.PaymentException;

import java.time.LocalDate;
import java.util.List;
import java.util.UUID;
public final class GetMyGivingHandler {
    public record Gift(UUID id, long amountMinor, String currency, LocalDate receivedOn, String method,
        boolean reversed, long refundedMinor) {}
    public record Result(DonorIdentity.Donor donor, List<OnlineFundDirectory.Entry> funds,
        List<PaymentGateway.Provider> providers, DonorPaymentReader.Page payments, List<Gift> gifts, long giftTotal) {}
    private final DonorIdentity donors;
    private final OnlineFundDirectory funds;
    private final PaymentGateway gateway;
    private final DonorPaymentReader payments;
    private final GivingReader giving;
    public GetMyGivingHandler(DonorIdentity donors, OnlineFundDirectory funds, PaymentGateway gateway,
        DonorPaymentReader payments, GivingReader giving) {
        this.donors = donors;
        this.funds = funds;
        this.gateway = gateway;
        this.payments = payments;
        this.giving = giving;
    }
    public Result handle(GetMyGivingQuery q) {
        DonorIdentity.Donor donor = donors.current().orElse(null);
        if (q.limit() < 1 || q.limit() > 100 || q.paymentOffset() < 0 || q.paymentOffset() > 100000
            || q.giftOffset() < 0 || q.giftOffset() > 100000) {
            throw PaymentException.invalid("Choose a valid giving history page.");
        }
        if (donor == null) {
            return new Result(null, List.of(), List.of(), new DonorPaymentReader.Page(List.of(), 0), List.of(), 0);
        }
        List<OnlineFundDirectory.Entry> catalog = funds.funds(false);
        if (catalog.size() > 500) {
            throw PaymentException.conflict("The online fund catalogue needs review. Contact the church office.");
        }
        GivingReader.GiftPage page = giving.gifts(new GivingReader.Filter(LocalDate.of(1900, 1, 1),
            LocalDate.of(9999, 12, 31), null, null, donor.id(), q.limit(), q.giftOffset()));
        return new Result(donor, catalog,
            gateway.providers().stream().filter(PaymentGateway.Provider::available).toList(),
            payments.forDonor(donor.id(), q.limit(), q.paymentOffset()),
            page.items()
                .stream()
                .map(g -> new Gift(g.id(), g.amountMinor(), g.currency(), g.receivedOn(), g.method(), g.reversed(),
                    g.refundedMinor()))
                .toList(),
            page.total());
    }
}
