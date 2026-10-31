package org.onechurch.communications.infrastructure;
import org.onechurch.communications.application.contract.PushSettings;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.stereotype.Component;

import java.util.Base64;
import java.util.UUID;
@Component
public final class ExpoPushSettings implements PushSettings {
    private final boolean enabled;
    private final UUID project;
    public ExpoPushSettings(@Value("${PUSH_ENABLED:false}") boolean enabled,
        @Value("${PUSH_PROJECT_ID:}") String project, @Value("${PUSH_TOKEN_KEY:}") String key) {
        boolean valid;
        try {
            valid = Base64.getDecoder().decode(key).length == 32;
        } catch (IllegalArgumentException e) {
            valid = false;
        }
        this.enabled = enabled && valid;
        this.project = project.isBlank() ? null : UUID.fromString(project);
    }
    public boolean enabled() {
        return enabled && project != null;
    }
    public UUID projectId() {
        return project;
    }
}
