package org.onechurch.communications.application.contract;
import java.util.Map;
/** Published rendering contract for originating contexts; uses the last published revision. */
public interface EmailTemplates {
    record Rendered(String key, long version, String subject, String html) {}
    Rendered render(String key, Map<String, String> values);
}
