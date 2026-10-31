package org.onechurch.payments.infrastructure;
import org.onechurch.giving.application.contract.DonorIdentity;
import org.onechurch.giving.application.contract.GivingReader;
import org.onechurch.giving.application.contract.OnlineFundDirectory;
import org.onechurch.payments.application.command.startdonorpayment.StartDonorPaymentHandler;
import org.onechurch.payments.application.command.startpayment.StartPaymentHandler;
import org.onechurch.payments.application.contract.DonorPaymentReader;
import org.onechurch.payments.application.contract.PaymentGateway;
import org.onechurch.payments.application.query.getdonorpayment.GetDonorPaymentHandler;
import org.onechurch.payments.application.query.getmygiving.GetMyGivingHandler;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
@Configuration
public class DonorPaymentConfiguration {
    @Bean
    StartDonorPaymentHandler startDonorPayment(DonorIdentity donors, StartPaymentHandler start) {
        return new StartDonorPaymentHandler(donors, start);
    }
    @Bean
    GetMyGivingHandler getMyGiving(DonorIdentity donors, OnlineFundDirectory funds, PaymentGateway gateway,
        DonorPaymentReader payments, GivingReader giving) {
        return new GetMyGivingHandler(donors, funds, gateway, payments, giving);
    }
    @Bean
    GetDonorPaymentHandler getDonorPayment(DonorIdentity donors, DonorPaymentReader reader) {
        return new GetDonorPaymentHandler(donors, reader);
    }
}
