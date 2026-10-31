package org.onechurch.payments.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.mockito.ArgumentMatchers.any;
import static org.mockito.ArgumentMatchers.anyLong;
import static org.mockito.ArgumentMatchers.anyString;
import static org.mockito.Mockito.doThrow;
import static org.mockito.Mockito.when;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_GIFTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_CHARGES;

import org.junit.jupiter.api.Test;
import org.onechurch.giving.application.contract.OnlineGivingLedger;
import org.onechurch.giving.domain.GivingException;
import org.onechurch.payments.application.contract.PaymentGateway;
import org.onechurch.shared.infrastructure.PaymentIntegrationTestSupport;
import org.springframework.test.context.bean.override.mockito.MockitoBean;
import org.springframework.test.context.bean.override.mockito.MockitoSpyBean;

import java.time.LocalDate;
import java.util.Map;
import java.util.UUID;
class PaymentPostingRecoveryIntegrationTest extends PaymentIntegrationTestSupport {
    @MockitoBean PaymentGateway gateway;
    @MockitoSpyBean OnlineGivingLedger giving;
    @Test
    void verifiedChargeSurvivesLedgerFailureAndAuthorizedReconciliationRetriesIt() throws Exception {
        when(gateway.mode("payfast")).thenReturn("live");
        UUID id = created(
            request("POST", "/api/payments", admin, Map.of("requestId", UUID.randomUUID(), "donorId", person("Donor"),
                "fundId", fund("General"), "amountMinor", 10000, "provider", "payfast", "recurring", false)));
        when(gateway.verify(anyString(), anyString(), any(), anyString())).thenReturn(new PaymentGateway.Notification(
            "payfast", "live", "one", "PAID", "one", null, id, 10000, "ZAR", null, LocalDate.now()));
        doThrow(GivingException.conflict("Temporary ledger conflict")).doCallRealMethod()
            .when(giving)
            .post(any(), any(), any(), anyLong(), anyString(), any(), any());
        assertThat(request("POST", "/api/payments/webhooks/payfast", null, Map.of()).status()).isEqualTo(204);
        assertThat(db.fetchCount(PAYMENTS_CHARGES)).isEqualTo(1);
        assertThat(db.fetchCount(GIVING_GIFTS)).isZero();
        UUID charge = db.select(PAYMENTS_CHARGES.ID).from(PAYMENTS_CHARGES).fetchOne(PAYMENTS_CHARGES.ID);
        assertThat(request("GET", "/api/payments/" + id, admin, null).body()).contains("needs ledger posting");
        created(request("POST", "/api/payments/" + id + "/reviews", admin,
            Map.of("requestId", UUID.randomUUID(), "chargeId", charge, "expectedVersion", 0, "settlementReference",
                "BANK-RECOVERED", "feeMinor", 0, "bankMinor", 10000, "note",
                "Provider confirms payment; retried ledger posting")));
        assertThat(db.fetchCount(GIVING_GIFTS)).isEqualTo(1);
        assertThat(db.fetchCount(PAYMENTS_CHARGES)).isEqualTo(1);
    }
}
