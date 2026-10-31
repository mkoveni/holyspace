package org.onechurch.payments.application.contract;
import org.onechurch.payments.domain.WebhookOperation;

import java.util.List;
import java.util.UUID;
public interface WebhookOperationReader {
    List<WebhookOperation> pending(String mode, UUID actor);
}
