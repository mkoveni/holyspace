package org.onechurch.communications.infrastructure;
import org.onechurch.communications.application.contract.DeliverySnapshot;
import org.onechurch.communications.application.contract.EmailTransport;
import org.springframework.beans.factory.ObjectProvider;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.mail.MailAuthenticationException;
import org.springframework.mail.MailException;
import org.springframework.mail.javamail.JavaMailSender;
import org.springframework.mail.javamail.JavaMailSenderImpl;
import org.springframework.stereotype.Component;

import jakarta.mail.internet.InternetAddress;
import jakarta.mail.internet.MimeMessage;

import java.nio.charset.StandardCharsets;
import java.util.Set;
@Component
public final class SmtpEmailTransport implements EmailTransport {
    private final ObjectProvider<JavaMailSender> sender;
    private final boolean enabled;
    private final boolean capture;
    private final String from;
    public SmtpEmailTransport(ObjectProvider<JavaMailSender> sender,
        @Value("${onechurch.email.enabled:false}") boolean enabled,
        @Value("${onechurch.email.capture:false}") boolean capture, @Value("${onechurch.email.from:}") String from) {
        this.sender = sender;
        this.enabled = enabled;
        this.capture = capture;
        this.from = from;
    }
    public Configuration configuration() {
        JavaMailSender mail = sender.getIfAvailable();
        boolean local = mail instanceof JavaMailSenderImpl smtp
            && Set.of("localhost", "127.0.0.1", "mailpit", "::1").contains(smtp.getHost());
        return new Configuration(enabled && mail != null && !from.isBlank() && (!capture || local), capture,
            capture ? "Local test inbox" : "SMTP");
    }
    public Result send(DeliverySnapshot delivery) {
        if (!configuration().enabled()) {
            return new Result("FAILED", "NOT_CONFIGURED", false);
        }
        try {
            JavaMailSender mail = sender.getObject();
            MimeMessage message = mail.createMimeMessage();
            message.setFrom(new InternetAddress(from, true));
            message.setRecipient(jakarta.mail.Message.RecipientType.TO,
                new InternetAddress(delivery.recipient(), true));
            message.setSubject(delivery.subject(), StandardCharsets.UTF_8.name());
            if (delivery.contentType().equals("text/html")) {
                message.setText(delivery.body(), StandardCharsets.UTF_8.name(), "html");
            } else {
                message.setText(delivery.body(), StandardCharsets.UTF_8.name());
            }
            message.saveChanges();
            message.setHeader("Message-ID", delivery.messageId());
            mail.send(message);
            return new Result("SENT", "", capture);
        } catch (MailAuthenticationException rejected) {
            return new Result("FAILED", "SMTP_AUTHENTICATION", false);
        } catch (MailException ambiguous) {
            return new Result("UNKNOWN", "SMTP_UNCERTAIN", false);
        } catch (jakarta.mail.MessagingException invalid) {
            return new Result("FAILED", "INVALID_EMAIL", false);
        }
    }
}
