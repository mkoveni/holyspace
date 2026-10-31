package org.onechurch.communications.infrastructure;
import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;
import org.junit.jupiter.params.ParameterizedTest;
import org.junit.jupiter.params.provider.ValueSource;
import org.onechurch.communications.application.contract.DeliverySnapshot;
import org.onechurch.communications.application.contract.EmailTransport;
import org.springframework.beans.factory.support.DefaultListableBeanFactory;
import org.springframework.mail.javamail.JavaMailSender;
import org.springframework.mail.javamail.JavaMailSenderImpl;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.PrintWriter;
import java.net.ServerSocket;
import java.net.Socket;
import java.nio.charset.StandardCharsets;
import java.time.Instant;
import java.util.UUID;
import java.util.concurrent.CompletableFuture;
import java.util.concurrent.TimeUnit;
class SmtpEmailTransportTest {
    @ParameterizedTest
    @ValueSource(strings = {"text/plain", "text/html"})
    void smtpUsesOneReviewedRecipientUtf8AndAStableMessageId(String contentType) throws Exception {
        try (ServerSocket server = new ServerSocket(0)) {
            CompletableFuture<String> received = CompletableFuture.supplyAsync(() -> receive(server));
            JavaMailSenderImpl mail = new JavaMailSenderImpl();
            mail.setHost("127.0.0.1");
            mail.setPort(server.getLocalPort());
            mail.getJavaMailProperties().setProperty("mail.smtp.timeout", "3000");
            mail.getJavaMailProperties().setProperty("mail.smtp.connectiontimeout", "3000");
            DefaultListableBeanFactory beans = new DefaultListableBeanFactory();
            beans.registerSingleton("mail", mail);
            SmtpEmailTransport transport = new SmtpEmailTransport(beans.getBeanProvider(JavaMailSender.class), true,
                true, "ministry@onechurch.test");
            Instant now = Instant.now();
            UUID id = UUID.randomUUID();
            DeliverySnapshot delivery = new DeliverySnapshot(id, UUID.randomUUID(), UUID.randomUUID(),
                UUID.randomUUID(), "guardian@example.org", "Ministry reminder", "Hello guardian, welcome to ministry.",
                "a".repeat(64), UUID.randomUUID(), now, now, "SENDING", 1, "<" + id + "@onechurch.delivery>",
                UUID.randomUUID(), now, null, "", false, 1, contentType, null, null);
            EmailTransport.Result result = transport.send(delivery);
            assertThat(result.status()).isEqualTo("SENT");
            assertThat(result.captured()).isTrue();
            assertThat(received.get(5, TimeUnit.SECONDS))
                .contains("To: guardian@example.org", "Message-ID: " + delivery.messageId(),
                    "Content-Type: " + contentType, "Hello guardian, welcome to ministry.")
                .doesNotContain("Bcc:", "Cc:");
        }
    }
    private static String receive(ServerSocket server) {
        try (Socket socket = server.accept();
            BufferedReader in =
                new BufferedReader(new InputStreamReader(socket.getInputStream(), StandardCharsets.UTF_8));
            PrintWriter out = new PrintWriter(socket.getOutputStream(), true, StandardCharsets.UTF_8)) {
            socket.setSoTimeout(5000);
            out.println("220 localhost test SMTP");
            StringBuilder payload = new StringBuilder();
            boolean data = false;
            String line;
            while ((line = in.readLine()) != null) {
                if (data) {
                    if (line.equals(".")) {
                        data = false;
                        out.println("250 accepted");
                    } else {
                        payload.append(line).append('\n');
                    }
                } else if (line.startsWith("EHLO") || line.startsWith("HELO")) {
                    out.println("250 localhost");
                } else if (line.startsWith("MAIL FROM") || line.startsWith("RCPT TO")) {
                    out.println("250 ok");
                } else if (line.equals("DATA")) {
                    data = true;
                    out.println("354 send message");
                } else if (line.equals("QUIT")) {
                    out.println("221 bye");
                    break;
                } else {
                    out.println("250 ok");
                }
            }
            return payload.toString();
        } catch (Exception error) {
            throw new IllegalStateException(error);
        }
    }
    @Test
    void captureModeCannotSendThroughAnExternalSmtpHost() {
        JavaMailSenderImpl mail = new JavaMailSenderImpl();
        mail.setHost("smtp.external.example");
        DefaultListableBeanFactory beans = new DefaultListableBeanFactory();
        beans.registerSingleton("mail", mail);
        SmtpEmailTransport transport =
            new SmtpEmailTransport(beans.getBeanProvider(JavaMailSender.class), true, true, "ministry@onechurch.test");
        assertThat(transport.configuration().enabled()).isFalse();
    }

}
