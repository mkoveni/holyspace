package org.onechurch.payments.application.contract;
import java.util.Map;
public interface CheckoutCodec {
    String encode(Map<String, String> fields);
    Map<String, String> decode(String fields);
}
