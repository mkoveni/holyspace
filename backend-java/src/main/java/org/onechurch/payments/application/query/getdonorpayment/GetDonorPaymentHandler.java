package org.onechurch.payments.application.query.getdonorpayment;
import org.onechurch.giving.application.contract.DonorIdentity;
import org.onechurch.payments.application.contract.DonorPaymentReader;
import org.onechurch.payments.domain.PaymentException;
public final class GetDonorPaymentHandler {
    private final DonorIdentity donors;
    private final DonorPaymentReader reader;
    public GetDonorPaymentHandler(DonorIdentity donors, DonorPaymentReader reader) {
        this.donors = donors;
        this.reader = reader;
    }
    public DonorPaymentReader.Detail handle(GetDonorPaymentQuery q) {
        DonorIdentity.Donor donor = donors.current().orElseThrow(PaymentException::missing);
        return reader.ownDetail(donor.id(), q.orderId()).orElseThrow(PaymentException::missing);
    }
}
