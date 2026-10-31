package org.onechurch.communications.application.contract;
import java.time.Instant;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface TemplateReader {
    record View(UUID id, String key, String name, String subject, String html, List<String> variables, String status,
        long version, Long publishedVersion, Instant updatedAt) {}
    record Page(List<View> templates, long total) {}
    record Revision(long version, String subject, String html, List<String> variables, Instant publishedAt) {}
    Optional<View> find(UUID id);
    Page list(String search, int limit, int offset);
    List<Revision> revisions(UUID id, int limit, int offset);
    Optional<Revision> published(String key);
}
