package org.onechurch.payments.domain.repository;
import org.onechurch.payments.domain.WebhookOperation;

import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface WebhookOperationRepository {
    Optional<WebhookOperation> find(UUID id);
    void insert(WebhookOperation operation);
    void finish(UUID id, String status, String webhookId, String encryptedSecret);
    List<WebhookOperation> pending(String mode, UUID actor);
    boolean referenced(String mode, String backendUrl);
}
