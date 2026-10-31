package org.onechurch.communications.domain;
import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;

import org.junit.jupiter.api.Test;

import java.util.List;
import java.util.Map;
class TemplateContentTest {
    @Test
    void variablesAreLiteralTextAndNeverRecursivelyEvaluated() {
        TemplateContent content = new TemplateContent("Hello {{name}}", "<p>{{name}}</p>", List.of("name"));
        TemplateContent.Rendered rendered =
            content.render(Map.of("name", "<img src=x onerror=alert(1)> & {{other}} $1"));
        assertThat(rendered.html()).contains("&lt;img", "&amp;", "{{other}}", "$1").doesNotContain("<img");
    }
    @Test
    void missingUnknownAndMalformedVariablesAreRejected() {
        assertThatThrownBy(() -> new TemplateContent("Hello", "<p>{{missing}}</p>", List.of()))
            .isInstanceOf(CommunicationException.class);
        assertThatThrownBy(() -> new TemplateContent("Hello", "<p>{{person.name}}</p>", List.of()))
            .isInstanceOf(CommunicationException.class);
        TemplateContent content = new TemplateContent("Hi {{name}}", "<p>{{name}}</p>", List.of("name"));
        assertThatThrownBy(() -> content.render(Map.of())).isInstanceOf(CommunicationException.class);
        assertThatThrownBy(() -> content.render(Map.of("name", "Alex", "other", "value")))
            .isInstanceOf(CommunicationException.class);
    }
    @Test
    void subjectHeaderInjectionIsRejectedAfterRendering() {
        TemplateContent content = new TemplateContent("Hi {{name}}", "<p>Hello</p>", List.of("name"));
        assertThatThrownBy(() -> content.render(Map.of("name", "Alex\r\nBcc: other@example.org")))
            .isInstanceOf(CommunicationException.class);
    }
}
