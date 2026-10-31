package org.onechurch.communications.domain;
import java.util.HashSet;
import java.util.List;
import java.util.Map;
import java.util.Set;
import java.util.regex.Matcher;
import java.util.regex.Pattern;
public record TemplateContent(String subject, String html, List<String> variables) {
    private static final Pattern TOKEN = Pattern.compile("\\{\\{\\s*([a-zA-Z][a-zA-Z0-9_]{0,49})\\s*\\}\\}");
    public TemplateContent {
        subject = CommunicationRules.text(subject, 200);
        html = CommunicationRules.text(html, 50000);
        if (subject.indexOf('\r') >= 0 || subject.indexOf('\n') >= 0) {
            throw CommunicationException.invalid("The subject must be a single line.");
        }
        if (variables == null || variables.size() > 30
            || variables.stream().anyMatch(v -> v == null || !v.matches("[a-zA-Z][a-zA-Z0-9_]{0,49}"))
            || new HashSet<>(variables).size() != variables.size()) {
            throw CommunicationException
                .invalid("Declare up to 30 unique variable names using letters, digits and underscores.");
        }
        variables = List.copyOf(variables);
        Set<String> used = new HashSet<>();
        Matcher matcher = TOKEN.matcher(subject + html);
        while (matcher.find()) {
            used.add(matcher.group(1));
        }
        if (!new HashSet<>(variables).equals(used)) {
            throw CommunicationException
                .invalid("Declared variables must exactly match the variables used in the subject and body.");
        }
        String remaining = TOKEN.matcher(subject + html).replaceAll("");
        if (remaining.contains("{{") || remaining.contains("}}")) {
            throw CommunicationException.invalid("Use variables in the form {{variableName}}.");
        }
    }
    public Rendered render(Map<String, String> values) {
        if (values == null || !values.keySet().equals(new HashSet<>(variables))
            || values.values().stream().anyMatch(v -> v == null || v.length() > 5000)) {
            throw CommunicationException
                .invalid("Supply every declared variable exactly once, with at most 5000 characters per value.");
        }
        String renderedSubject = replace(subject, values, false);
        if (renderedSubject.length() > 200 || renderedSubject.indexOf('\r') >= 0
            || renderedSubject.indexOf('\n') >= 0) {
            throw CommunicationException
                .invalid("The rendered subject must be a single line of at most 200 characters.");
        }
        String renderedHtml = replace(html, values, true);
        if (renderedHtml.length() > 200000) {
            throw CommunicationException.invalid("Rendered message is too large.");
        }
        return new Rendered(renderedSubject, renderedHtml);
    }
    private static String replace(String source, Map<String, String> values, boolean html) {
        Matcher matcher = TOKEN.matcher(source);
        StringBuilder result = new StringBuilder();
        while (matcher.find()) {
            String value = values.get(matcher.group(1));
            matcher.appendReplacement(result, Matcher.quoteReplacement(html ? escape(value) : value));
        }
        matcher.appendTail(result);
        return result.toString();
    }
    private static String escape(String value) {
        return value.replace("&", "&amp;")
            .replace("<", "&lt;")
            .replace(">", "&gt;")
            .replace("\"", "&quot;")
            .replace("'", "&#39;");
    }
    public record Rendered(String subject, String html) {}
}
