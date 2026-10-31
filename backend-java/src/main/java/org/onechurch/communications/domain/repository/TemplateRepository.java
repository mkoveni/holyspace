package org.onechurch.communications.domain.repository;
import org.onechurch.communications.domain.EmailTemplate;

import java.util.Optional;
import java.util.UUID;
public interface TemplateRepository {
    Optional<EmailTemplate> lock(UUID id);
    void insert(EmailTemplate template);
    void save(EmailTemplate template);
    void publish(EmailTemplate template);
}
