package org.onechurch.communications.infrastructure;
import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.AfterEach;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.onechurch.communications.application.contract.PushTransport;

import com.sun.net.httpserver.HttpServer;

import tools.jackson.databind.json.JsonMapper;

import java.net.InetSocketAddress;
import java.nio.charset.StandardCharsets;
import java.util.UUID;
class ExpoPushTransportTest {
    private HttpServer server;
    private ExpoPushTransport transport;
    private String request;
    private int status;
    private String response;
    @BeforeEach
    void start() throws Exception {
        server = HttpServer.create(new InetSocketAddress("127.0.0.1", 0), 0);
        server.createContext("/push", exchange -> {
            request = new String(exchange.getRequestBody().readAllBytes(), StandardCharsets.UTF_8);
            byte[] data = response.getBytes(StandardCharsets.UTF_8);
            exchange.sendResponseHeaders(status, data.length);
            exchange.getResponseBody().write(data);
            exchange.close();
        });
        server.start();
        transport = new ExpoPushTransport("http://127.0.0.1:" + server.getAddress().getPort() + "/push", "",
            JsonMapper.builder().build());
        status = 200;
    }
    @AfterEach
    void stop() {
        server.stop(0);
    }
    @Test
    void payloadIsGenericAndOnlyCarriesAnOpaqueNoticeIdentifier() {
        response = "{\"data\":{\"status\":\"ok\",\"id\":\"ticket-1\"}}";
        UUID id = UUID.randomUUID();
        PushTransport.Result result = transport.send("ExpoPushToken[example_token]", id);
        assertThat(result.state()).isEqualTo("ACCEPTED");
        assertThat(request).contains(id.toString(), "holyspace.member-update.v1", "member-updates")
            .doesNotContain("accountId", "childId", "destination", "credential");
    }
    @Test
    void receiptAndInvalidTokenAreDifferentFromProviderQueueAcceptance() {
        response = "{\"data\":{\"ticket-1\":{\"status\":\"error\",\"details\":{\"error\":\"DeviceNotRegistered\"}}}}";
        assertThat(transport.receipt("ticket-1").errorCode()).isEqualTo("DeviceNotRegistered");
        response = "{\"data\":{}}";
        assertThat(transport.receipt("ticket-1").state()).isEqualTo("PENDING");
    }
    @Test
    void uncertainSendsAreNotAutomaticallyClassifiedAsSafeRetries() {
        response = "{}";
        status = 503;
        assertThat(transport.send("token", UUID.randomUUID()).state()).isEqualTo("UNKNOWN");
        assertThat(transport.receipt("ticket-1").state()).isEqualTo("RETRY");
        status = 429;
        assertThat(transport.send("token", UUID.randomUUID()).state()).isEqualTo("RETRY");
    }
}
