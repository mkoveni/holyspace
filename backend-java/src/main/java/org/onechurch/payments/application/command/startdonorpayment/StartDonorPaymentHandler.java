package org.onechurch.payments.application.command.startdonorpayment;
import org.onechurch.giving.application.contract.DonorIdentity;
import org.onechurch.payments.application.command.startpayment.StartPaymentCommand;
import org.onechurch.payments.application.command.startpayment.StartPaymentHandler;

import java.util.UUID;
public final class StartDonorPaymentHandler {
    private final DonorIdentity donors;
    private final StartPaymentHandler start;
    public StartDonorPaymentHandler(DonorIdentity donors, StartPaymentHandler start) {
        this.donors = donors;
        this.start = start;
    }
    public UUID handle(StartDonorPaymentCommand c) {
        UUID donor = donors.requireOwn(c.actorId(), null);
        return start.handle(new StartPaymentCommand(c.actorId(), c.requestId(), donor, c.fundId(), c.amountMinor(),
            c.provider(), c.recurring(), true));
    }
}
