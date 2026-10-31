package org.onechurch.communications.infrastructure;
import org.jsoup.Jsoup;
import org.jsoup.nodes.Document;
import org.jsoup.nodes.Element;
import org.jsoup.safety.Safelist;
import org.onechurch.communications.application.contract.RichText;
import org.onechurch.communications.domain.CommunicationException;
import org.springframework.stereotype.Component;
@Component
public final class SafeEmailHtml implements RichText {
    public String sanitize(String html) {
        if (html == null || html.length() > 200000) {
            throw CommunicationException.invalid("Enter an email body of at most 200000 characters.");
        }
        Safelist allowed = Safelist.none()
            .addTags("p", "br", "strong", "em", "u", "s", "ul", "ol", "li", "h2", "h3", "blockquote", "a")
            .addAttributes("a", "href", "title")
            .addProtocols("a", "href", "https", "http", "mailto")
            .addEnforcedAttribute("a", "rel", "noopener noreferrer");
        Document dirty = Jsoup.parseBodyFragment(html);
        for (Element element : dirty.getAllElements()) {
            for (org.jsoup.nodes.Attribute attribute : element.attributes()) {
                if (attribute.getValue().contains("{{")) {
                    throw CommunicationException
                        .invalid("Insert variables in text, never in link addresses or HTML attributes.");
                }
            }
        }
        String clean = Jsoup.clean(html, "", allowed, new Document.OutputSettings().prettyPrint(false));
        if (Jsoup.parseBodyFragment(clean).text().isBlank()) {
            throw CommunicationException.invalid("Add some text to the email body.");
        }
        return clean;
    }
}
