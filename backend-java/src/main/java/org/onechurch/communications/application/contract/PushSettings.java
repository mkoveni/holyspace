package org.onechurch.communications.application.contract;
import java.util.UUID;
public interface PushSettings {
    boolean enabled();
    UUID projectId();
}
