package org.onechurch.payments.infrastructure.gateway;
import java.util.Map;
public interface ProviderHttp {
    String send(String method, String url, Map<String, String> headers, String body);
}
