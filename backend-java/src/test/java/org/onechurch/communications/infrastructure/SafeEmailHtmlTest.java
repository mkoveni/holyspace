package org.onechurch.communications.infrastructure;
import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;

import org.junit.jupiter.api.Test;
import org.onechurch.communications.domain.CommunicationException;
class SafeEmailHtmlTest {
    private final SafeEmailHtml html = new SafeEmailHtml();
    @Test
    void scriptsImagesEventHandlersStylesAndUnsafeProtocolsAreRemoved() {
        String safe = html.sanitize(
            "<h2>Hello</h2><p onclick='alert(1)' style='color:red'>{{name}}<script>alert(1)</script><img src='https://tracker.test/x'><a href='javascript:alert(1)'>Bad</a><a href='https://church.test'>Visit</a></p>");
        assertThat(safe).contains("<h2>Hello</h2>", "{{name}}", "https://church.test", "noopener noreferrer")
            .doesNotContain("script", "onclick", "style=", "<img", "javascript:");
    }
    @Test
    void variablesCannotBecomeLinkAddressesOrAttributeValues() {
        assertThatThrownBy(() -> html.sanitize("<p><a href='{{url}}'>Visit</a></p>"))
            .isInstanceOf(CommunicationException.class);
    }
}
