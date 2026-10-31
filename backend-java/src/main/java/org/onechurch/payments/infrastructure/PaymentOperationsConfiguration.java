package org.onechurch.payments.infrastructure;
import org.onechurch.communications.application.contract.DeliveryReader;
import org.onechurch.communications.application.contract.EmailDeliveries;
import org.onechurch.communications.application.contract.EmailTemplates;
import org.onechurch.payments.application.PaymentAccess;
import org.onechurch.payments.application.PaymentReceiptPreview;
import org.onechurch.payments.application.YocoWebhookManagement;
import org.onechurch.payments.application.command.approvepaymentreceipt.ApprovePaymentReceiptHandler;
import org.onechurch.payments.application.command.closewebhookoperation.CloseWebhookOperationHandler;
import org.onechurch.payments.application.command.registeryocowebhook.RegisterYocoWebhookHandler;
import org.onechurch.payments.application.command.removeyocowebhook.RemoveYocoWebhookHandler;
import org.onechurch.payments.application.command.retrypaymentreceipt.RetryPaymentReceiptHandler;
import org.onechurch.payments.application.command.saveprovidersettings.SaveProviderSettingsHandler;
import org.onechurch.payments.application.contract.PaymentCredentialVault;
import org.onechurch.payments.application.contract.PaymentReader;
import org.onechurch.payments.application.contract.PaymentReceiptReader;
import org.onechurch.payments.application.contract.ProviderConfigurationReader;
import org.onechurch.payments.application.contract.WebhookOperationReader;
import org.onechurch.payments.application.contract.YocoWebhookReader;
import org.onechurch.payments.application.contract.YocoWebhookWriter;
import org.onechurch.payments.application.query.getpaymentreceipt.GetPaymentReceiptHandler;
import org.onechurch.payments.application.query.listyocowebhooks.ListYocoWebhooksHandler;
import org.onechurch.payments.domain.repository.PaymentReceiptRepository;
import org.onechurch.payments.domain.repository.PaymentRepository;
import org.onechurch.payments.domain.repository.ProviderConfigurationRepository;
import org.onechurch.payments.domain.repository.WebhookOperationRepository;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.people.application.contract.PersonEmailDirectory;
import org.onechurch.shared.application.UnitOfWork;
import org.springframework.beans.factory.annotation.Qualifier;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import java.time.Clock;
@Configuration
public class PaymentOperationsConfiguration {
    @Bean
    YocoWebhookManagement yocoWebhookManagement(ProviderConfigurationRepository configurations,
        WebhookOperationRepository operations, PaymentCredentialVault vault, YocoWebhookReader reader,
        YocoWebhookWriter writer, SaveProviderSettingsHandler save, UnitOfWork tx, Clock clock, PaymentAccess access) {
        return new YocoWebhookManagement(configurations, operations, vault, reader, writer, save, tx, clock, access);
    }
    @Bean
    ListYocoWebhooksHandler listYocoWebhooksHandler(PaymentAccess access, ProviderConfigurationReader configurations,
        PaymentCredentialVault vault, WebhookOperationReader operations, YocoWebhookReader reader) {
        return new ListYocoWebhooksHandler(access, configurations, vault, operations, reader);
    }
    @Bean
    PaymentReceiptPreview paymentReceiptPreview(PaymentReader payments, PersonDirectory people,
        PersonEmailDirectory emails, EmailTemplates templates) {
        return new PaymentReceiptPreview(payments, people, emails, templates);
    }
    @Bean
    GetPaymentReceiptHandler getPaymentReceiptHandler(PaymentAccess access, PaymentReceiptPreview previews,
        PaymentReceiptReader receipts, @Qualifier("communicationDeliveryStore") DeliveryReader deliveries) {
        return new GetPaymentReceiptHandler(access, previews, receipts, deliveries);
    }
    @Bean
    ApprovePaymentReceiptHandler approvePaymentReceiptHandler(PaymentAccess access, PaymentReceiptPreview previews,
        PaymentReceiptReader reader, PaymentReceiptRepository writer, PaymentRepository payments,
        PersonDirectory people, EmailDeliveries deliveries, UnitOfWork tx, Clock clock) {
        return new ApprovePaymentReceiptHandler(access, previews, reader, writer, payments, people, deliveries, tx,
            clock);
    }
    @Bean
    RetryPaymentReceiptHandler retryPaymentReceiptHandler(PaymentAccess access, PaymentReceiptReader reader,
        PaymentReceiptPreview previews, PaymentRepository payments, EmailDeliveries deliveries, UnitOfWork tx) {
        return new RetryPaymentReceiptHandler(access, reader, previews, payments, deliveries, tx);
    }
    @Bean
    RegisterYocoWebhookHandler registerYocoWebhookHandler(YocoWebhookManagement service) {
        return new RegisterYocoWebhookHandler(service);
    }
    @Bean
    RemoveYocoWebhookHandler removeYocoWebhookHandler(YocoWebhookManagement service) {
        return new RemoveYocoWebhookHandler(service);
    }
    @Bean
    CloseWebhookOperationHandler closeWebhookOperationHandler(YocoWebhookManagement service) {
        return new CloseWebhookOperationHandler(service);
    }
}
