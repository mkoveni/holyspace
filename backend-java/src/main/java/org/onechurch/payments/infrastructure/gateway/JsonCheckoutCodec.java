package org.onechurch.payments.infrastructure.gateway;
import org.onechurch.payments.application.contract.CheckoutCodec;
import org.springframework.stereotype.Component;

import tools.jackson.core.type.TypeReference;
import tools.jackson.databind.json.JsonMapper;

import java.util.Map;
@Component
public final class JsonCheckoutCodec implements CheckoutCodec {
    private final JsonMapper json = JsonMapper.builder().build();
    @Override
    public String encode(Map<String, String> fields) {
        return json.writeValueAsString(fields);
    }
    @Override
    public Map<String, String> decode(String fields) {
        return fields == null ? Map.of() : json.readValue(fields, new TypeReference<Map<String, String>>() {
        });
    }
}
