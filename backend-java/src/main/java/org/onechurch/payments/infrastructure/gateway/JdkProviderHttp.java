package org.onechurch.payments.infrastructure.gateway;
import org.onechurch.payments.domain.PaymentException;
import org.springframework.stereotype.Component;

import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.time.Duration;
import java.util.Map;
@Component
public final class JdkProviderHttp implements ProviderHttp {
    private final HttpClient http = HttpClient.newBuilder()
        .connectTimeout(Duration.ofSeconds(7))
        .followRedirects(HttpClient.Redirect.NEVER)
        .build();
    @Override
    public String send(String method, String url, Map<String, String> headers, String body) {
        try {
            HttpRequest.Builder request = HttpRequest.newBuilder(URI.create(url)).timeout(Duration.ofSeconds(10));
            headers.forEach(request::header);
            request.method(method,
                body.isEmpty() ? HttpRequest.BodyPublishers.noBody() : HttpRequest.BodyPublishers.ofString(body));
            HttpResponse<String> response = http.send(request.build(), HttpResponse.BodyHandlers.ofString());
            if (response.statusCode() < 200 || response.statusCode() > 299 || response.body().length() > 1000000) {
                throw PaymentException.unavailable("Provider request could not be confirmed. Retry the same request.");
            }
            return response.body();
        } catch (InterruptedException error) {
            Thread.currentThread().interrupt();
            throw PaymentException.unavailable("Payment provider request interrupted. Retry the same request.");
        } catch (java.io.IOException error) {
            throw PaymentException.unavailable("Payment provider is temporarily unavailable. Retry the same request.");
        }
    }

}
