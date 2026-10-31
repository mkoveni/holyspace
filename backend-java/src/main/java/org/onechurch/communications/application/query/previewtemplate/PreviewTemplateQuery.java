package org.onechurch.communications.application.query.previewtemplate;
import java.util.List;
import java.util.Map;
public record PreviewTemplateQuery(String subject, String html, List<String> variables, Map<String, String> values) {}
