package org.onechurch.payments.application.contract;
import java.util.List;
public interface YocoWebhookReader {
    record Entry(String id, String name, String url, String mode) {}
    List<Entry> list(String secretKey);
}
