import java.time.*;
import java.util.*;
import org.onechurch.payments.application.PaymentHash;
import org.onechurch.payments.application.contract.PaymentGateway;
import org.onechurch.payments.domain.PaymentOrder;
import org.onechurch.payments.domain.ProviderCredentials;
import org.onechurch.payments.infrastructure.gateway.AesSubscriptionVault;
import org.onechurch.payments.infrastructure.gateway.EncryptedPaymentCredentials;
import org.onechurch.payments.infrastructure.gateway.HostedPaymentGateway;
import org.onechurch.payments.infrastructure.gateway.PaymentSettings;

/**
 * Prints the Java-captured vectors in tests/Payments (PaymentHashTest, AesSubscriptionVaultTest,
 * HostedPaymentGatewayTest). From backend-java, with a JDK, a compiled target/classes and the jar's libraries
 * extracted to /tmp/jar (unzip target/backend-java-0.0.1-SNAPSHOT.jar 'BOOT-INF/lib/*' -d /tmp/jar):
 *   javac -cp "target/classes:/tmp/jar/BOOT-INF/lib/*" -d /tmp/v ../backend-symfony/docs/porting/vectors/PaymentVectors.java
 *   java -cp "target/classes:/tmp/jar/BOOT-INF/lib/*:/tmp/v" PaymentVectors
 * The cipher lines are random on every run; any run's output is a valid vector.
 */
public class PaymentVectors {
    public static void main(String[] a) throws Exception {
        UUID actor = UUID.fromString("11111111-1111-4111-8111-111111111111");
        UUID donor = UUID.fromString("22222222-2222-4222-8222-222222222222");
        UUID fund = UUID.fromString("33333333-3333-4333-8333-333333333333");
        UUID order = UUID.fromString("44444444-4444-4444-8444-444444444444");
        UUID charge = UUID.fromString("55555555-5555-4555-8555-555555555555");
        System.out.println("checkout " + PaymentHash.of("checkout", actor, donor, fund, 10000L, "payfast", "live", false));
        System.out.println("donorCheckout " + PaymentHash.of("donor-checkout", actor, donor, fund, 25050L, "yoco", "test", true));
        System.out.println("notification " + PaymentHash.of("payfast", "live", "PAID", "12345", null, order, 10000L, "ZAR",
            PaymentHash.of("12345678-1234-1234-1234-123456789012"), LocalDate.of(2026, 10, 28)));
        System.out.println("token " + PaymentHash.of("12345678-1234-1234-1234-123456789012"));
        System.out.println("settlement " + PaymentHash.of("settlement", actor, order, charge, 0L, "BANK-001", 300L, 9700L,
            "Matched bank and provider statement"));
        System.out.println("unicode " + PaymentHash.of(actor, "Grüße 漢字 😀", "", null));
        System.out.println("credentials " + PaymentHash.of("10000100", "merchant-key", "passphrase", "", ""));
        System.out.println("webhook " + PaymentHash.of(actor, "test", "REMOVE", "sub_old"));
        System.out.println("webhookNull " + PaymentHash.of(actor, "live", "REGISTER", null));

        String key = Base64.getEncoder().encodeToString("0123456789abcdef0123456789abcdef".getBytes());
        PaymentSettings settings = new PaymentSettings("https://church.example", "test", "10000100", "merchant-key",
            "pass phrase*~", "127.0.0.1/32", "test", "sk_test_example", "whsec_x", key, "https://api.church.example");
        AesSubscriptionVault vault = new AesSubscriptionVault(settings);
        System.out.println("key " + key);
        System.out.println("tokenCipher " + vault.encrypt("12345678-1234-1234-1234-123456789012"));
        EncryptedPaymentCredentials credentials = new EncryptedPaymentCredentials(settings, vault);
        String encrypted = credentials.encrypt(new ProviderCredentials("10000100", "merchant-key", "pass phrase", null, null));
        System.out.println("credentialCipher " + encrypted);
        System.out.println("credentialJson " + vault.decrypt(encrypted));

        Clock clock = Clock.fixed(Instant.parse("2026-10-28T22:30:00Z"), ZoneOffset.UTC);
        HostedPaymentGateway gateway = new HostedPaymentGateway(settings, clock, ZoneId.of("Africa/Johannesburg"),
            (method, url, headers, body) -> {
                throw new AssertionError();
            });
        PaymentOrder monthly = new PaymentOrder(order, donor, fund, 12345, "payfast", "test", true, "PENDING", null, null,
            null, "PENDING", null, null, null, 0, "h".repeat(64), actor, clock.instant(), null, true);
        PaymentGateway.Checkout checkout = monthly == null ? null : gateway.checkout(monthly, "Thandi O'Neil & Co", "donor@example.org");
        checkout.fields().forEach((k, v) -> System.out.println("field " + k + "=" + v));
    }
}
