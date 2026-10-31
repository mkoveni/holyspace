package org.onechurch.identityaccess.application.contract;

import java.util.Objects;
import java.util.Optional;
import java.util.UUID;

public interface CurrentActor {

    record Actor(UUID accountId, UUID sessionId) {
        public Actor {
            Objects.requireNonNull(accountId);
            Objects.requireNonNull(sessionId);
        }
    }

    Optional<Actor> current();
}
