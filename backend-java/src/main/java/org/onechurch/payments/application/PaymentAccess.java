package org.onechurch.payments.application;
import org.onechurch.giving.application.contract.DonorIdentity;
import org.onechurch.giving.application.contract.DonorIdentityGuard;
import org.onechurch.giving.application.contract.OnlineFundDirectory;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.payments.domain.PaymentException;
import org.onechurch.payments.domain.PaymentOrder;

import java.util.UUID;
public final class PaymentAccess {
    private final PermissionChecker auth;
    private final DonorIdentity donors;
    private final DonorIdentityGuard guard;
    private final OnlineFundDirectory funds;
    public PaymentAccess(PermissionChecker auth, DonorIdentity donors, DonorIdentityGuard guard,
        OnlineFundDirectory funds) {
        this.auth = auth;
        this.donors = donors;
        this.guard = guard;
        this.funds = funds;
    }
    public void start(UUID actor, UUID donor, UUID fund, boolean self, boolean locked) {
        if (!self) {
            manage(actor);
            return;
        }
        if (donors == null) {
            throw PaymentException.missing();
        }
        if (locked) {
            guard.lockOwn(actor, donor);
        } else {
            donors.requireOwn(actor, donor);
        }
        if (!funds.enabled(fund)) {
            throw PaymentException.invalid("This fund is not currently offered online.");
        }
    }
    public void order(UUID actor, PaymentOrder order, boolean self, boolean locked) {
        if (!self) {
            manage(actor);
            return;
        }
        if (donors == null) {
            throw PaymentException.missing();
        }
        if (!donors.requireOwn(actor, null).equals(order.donorId())) {
            throw PaymentException.missing();
        }
        if (locked) {
            guard.lockOwn(actor, order.donorId());
        } else {
            donors.requireOwn(actor, order.donorId());
        }
    }

    public PaymentAccess(PermissionChecker auth) {
        this(auth, null, null, null);
    }
    public void caller(UUID actor, boolean self) {
        if (!self) {
            manage(actor);
            return;
        }
        if (donors == null) {
            throw PaymentException.missing();
        }
        donors.requireOwn(actor, null);
    }
    public void configureRead() {
        auth.requirePermission("payments.configure");
    }
    public void configure(UUID actor) {
        auth.requirePermission(actor, "payments.configure");
    }
    public void receiptsRead() {
        read();
        auth.requirePermission("payments.receipts.send");
    }
    public void receipts(UUID actor) {
        manage(actor);
        auth.requirePermission(actor, "payments.receipts.send");
    }
    public void read() {
        auth.requirePermission("payments.manage");
    }
    public void manage(UUID actor) {
        auth.requirePermission(actor, "payments.manage");
    }
    public void refund(UUID actor) {
        manage(actor);
        auth.requirePermission(actor, "payments.refund");
    }
    public void reconcile(UUID actor) {
        manage(actor);
        auth.requirePermission(actor, "payments.reconcile");
    }
}
