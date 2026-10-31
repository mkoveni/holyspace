package org.onechurch.payments.application;
import org.onechurch.payments.application.command.saveprovidersettings.SaveProviderSettingsCommand;
import org.onechurch.payments.application.command.saveprovidersettings.SaveProviderSettingsHandler;
import org.onechurch.payments.application.contract.PaymentCredentialVault;
import org.onechurch.payments.application.contract.YocoWebhookReader;
import org.onechurch.payments.application.contract.YocoWebhookWriter;
import org.onechurch.payments.domain.ConfigurationCheck;
import org.onechurch.payments.domain.PaymentException;
import org.onechurch.payments.domain.PaymentOrder;
import org.onechurch.payments.domain.ProviderConfiguration;
import org.onechurch.payments.domain.ProviderCredentials;
import org.onechurch.payments.domain.ProviderSettings;
import org.onechurch.payments.domain.WebhookOperation;
import org.onechurch.payments.domain.repository.ProviderConfigurationRepository;
import org.onechurch.payments.domain.repository.WebhookOperationRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.util.List;
import java.util.UUID;
public final class YocoWebhookManagement {
    public record Pending(UUID requestId, String kind, String targetId, String status, Instant createdAt) {}
    public record Listing(List<YocoWebhookReader.Entry> subscriptions, List<Pending> pending) {}
    public record Result(UUID requestId, String status, String message) {}
    private final ProviderConfigurationRepository configurations;
    private final WebhookOperationRepository operations;
    private final PaymentCredentialVault vault;
    private final YocoWebhookReader reader;
    private final YocoWebhookWriter writer;
    private final SaveProviderSettingsHandler save;
    private final UnitOfWork tx;
    private final Clock clock;
    private final PaymentAccess access;
    public YocoWebhookManagement(ProviderConfigurationRepository configurations, WebhookOperationRepository operations,
        PaymentCredentialVault vault, YocoWebhookReader reader, YocoWebhookWriter writer,
        SaveProviderSettingsHandler save, UnitOfWork tx, Clock clock, PaymentAccess access) {
        this.configurations = configurations;
        this.operations = operations;
        this.vault = vault;
        this.reader = reader;
        this.writer = writer;
        this.save = save;
        this.tx = tx;
        this.clock = clock;
        this.access = access;
    }
    private ProviderConfiguration configuration(String mode) {
        ProviderSettings.validate("yoco", mode);
        ProviderConfiguration c = configurations.latest("yoco", mode)
            .orElseThrow(() -> PaymentException.conflict("Save the backend URL and Yoco secret API key first."));
        key(c);
        return c;
    }
    private String key(ProviderConfiguration c) {
        String key = vault.decrypt(c.encryptedCredentials()).yocoKey();
        if (!key.startsWith("sk_" + c.mode() + "_")) {
            throw PaymentException.conflict("Save a Yoco secret API key for this environment first.");
        }
        return key;
    }
    private void protectReason(String note, ProviderConfiguration configuration) {
        ProviderCredentials credentials = vault.decrypt(configuration.encryptedCredentials());
        if (List.of(credentials.yocoKey(), credentials.webhookSecret())
            .stream()
            .filter(v -> v.length() >= 4)
            .anyMatch(note::contains)) {
            throw PaymentException.invalid("Keep credentials out of the change reason.");
        }
    }
    public void close(UUID actor, UUID request, long expected, String reason) {
        access.configure(actor);
        String note = PaymentOrder.text(reason, 500, "Provider review evidence");
        tx.execute(() -> {
            ProviderSettings head = configurations.lock("yoco");
            access.configure(actor);
            head.expected(expected);
            WebhookOperation operation = operations.find(request).orElseThrow(PaymentException::missing);
            boolean interrupted = operation.status().equals("STARTED")
                && !operation.createdAt().isAfter(clock.instant().minusSeconds(300));
            if (!List.of("UNKNOWN", "READY").contains(operation.status()) && !interrupted) {
                throw PaymentException.conflict(
                    "An in-progress operation can be closed after five minutes. Review provider state before closing.");
            }
            ProviderConfiguration current = configuration(operation.mode());
            protectReason(note, current);
            protectReason(note, configurations.revision(operation.revisionId()).orElseThrow());
            if (operation.encryptedSecret() != null) {
                String secret = vault.decrypt(operation.encryptedSecret()).webhookSecret();
                if (secret.length() >= 4 && note.contains(secret)) {
                    throw PaymentException.invalid("Keep credentials out of the change reason.");
                }
            }
            configurations.check(new ConfigurationCheck(UUID.randomUUID(), current.id(), false,
                "Webhook operation closed after manual review. Test the connection again.", actor, clock.instant()));
            configurations
                .save(new ProviderSettings("yoco", head.mode(), false, false, head.managed(), head.version() + 1));
            operations.finish(request, "CLOSED", operation.webhookId(), operation.encryptedSecret());
            configurations.audit("yoco", operation.mode(), head.version() + 1, "WEBHOOK_REVIEWED", note,
                "webhook operation", actor, clock.instant());
            return null;
        });
    }
    public Result change(UUID actor, UUID request, String mode, long expected, String kind, String target,
        String reason) {
        access.configure(actor);
        ProviderSettings.validate("yoco", mode);
        String note = PaymentOrder.text(reason, 500, "Webhook change reason");
        if (request == null || (!kind.equals("REGISTER") && !kind.equals("REMOVE"))) {
            throw PaymentException.invalid("Choose a webhook operation.");
        }
        if (kind.equals("REMOVE") && (target == null || !target.matches("sub_[A-Za-z0-9_-]{1,150}"))) {
            throw PaymentException.invalid("Choose a valid Yoco subscription.");
        }
        String hash = PaymentHash.of(actor, mode, kind, target);
        boolean[] created = {false};
        WebhookOperation operation = tx.execute(() -> {
            ProviderSettings head = configurations.lock("yoco");
            access.configure(actor);
            head.expected(expected);
            protectReason(note, configuration(mode));
            WebhookOperation prior = operations.find(request).orElse(null);
            if (prior != null) {
                if (!prior.requestHash().equals(hash)) {
                    throw PaymentException.conflict("Webhook request identifier already used for different details.");
                }
                protectReason(note, configurations.revision(prior.revisionId()).orElseThrow());
                return prior;
            }
            ProviderConfiguration c = configuration(mode);
            if (kind.equals("REGISTER") && head.enabled()) {
                throw PaymentException.conflict("Disable new Yoco checkouts and save before registering a webhook.");
            }
            ProviderCredentials credentials = vault.decrypt(c.encryptedCredentials());
            if (List.of(credentials.yocoKey(), credentials.webhookSecret())
                .stream()
                .filter(v -> v.length() >= 4)
                .anyMatch(note::contains)) {
                throw PaymentException.invalid("Keep credentials out of the change reason.");
            }
            WebhookOperation fresh = new WebhookOperation(request, c.id(), mode, kind, target, c.backendUrl(), hash,
                actor, "STARTED", null, null, clock.instant());
            operations.insert(fresh);
            created[0] = true;
            return fresh;
        });
        if (operation.status().equals("CLOSED")) {
            throw PaymentException
                .conflict("This operation was closed. Use a new request after reviewing the provider list.");
        }
        if (operation.status().equals("APPLIED")) {
            return new Result(request, "APPLIED", "Webhook change already completed.");
        }
        ProviderConfiguration original = configurations.revision(operation.revisionId()).orElseThrow();
        if (kind.equals("REGISTER")) {
            if (created[0]) {
                try {
                    YocoWebhookWriter.Registered registered =
                        writer.register(key(original), "onechurch-" + mode + "-" + request,
                            original.backendUrl() + "/api/payments/webhooks/yoco", mode);
                    String secret = vault.encrypt(new ProviderCredentials(null, null, null, null, registered.secret()));
                    tx.execute(() -> {
                        operations.finish(request, "READY", registered.id(), secret);
                        return null;
                    });
                } catch (RuntimeException failure) {
                    tx.execute(() -> {
                        operations.finish(request, "UNKNOWN", null, null);
                        return null;
                    });
                    return new Result(request, "UNKNOWN",
                        "Registration outcome is uncertain. Refresh the webhook list before creating another subscription; this request will not register twice.");
                }
            }
            WebhookOperation ready = operations.find(request).orElseThrow();
            if (!ready.status().equals("READY")) {
                return new Result(request, ready.status(),
                    "Registration is in progress or uncertain. Inspect the webhook list; this request will not register twice.");
            }
            try {
                return tx.execute(() -> {
                    ProviderSettings head = configurations.lock("yoco");
                    access.configure(actor);
                    head.expected(expected);
                    ProviderConfiguration current = configuration(mode);
                    if (!current.backendUrl().equals(original.backendUrl()) || !key(current).equals(key(original))) {
                        throw PaymentException.conflict(
                            "Backend URL or API key changed. The registered secret is retained securely; restore the matching settings before finishing this registration.");
                    }
                    String secret = vault.decrypt(ready.encryptedSecret()).webhookSecret();
                    save.handle(new SaveProviderSettingsCommand(actor, "yoco", mode, expected, false, false,
                        current.publicUrl(), current.ipRanges(),
                        new ProviderCredentials(null, null, null, null, secret), note, current.backendUrl()));
                    operations.finish(request, "APPLIED", ready.webhookId(), ready.encryptedSecret());
                    configurations.audit("yoco", mode, expected + 1, "WEBHOOK_REGISTERED", note, "webhook registration",
                        actor, clock.instant());
                    return new Result(request, "APPLIED",
                        "Webhook registered and signing secret saved securely. Test the saved connection before activation.");
                });
            } catch (PaymentException stale) {
                return new Result(request, "READY",
                    "Webhook registered; its signing secret is retained securely. Reload settings and finish saving this registration before activation.");
            }
        }
        // A removal is always checked against current provider evidence, including explicit retries after a timeout.
        try {
            YocoWebhookReader.Entry found = reader.list(key(original))
                .stream()
                .filter(v -> v.id().equals(target) && v.mode().equals(mode))
                .findFirst()
                .orElse(null);
            if (found != null) {
                String backend = found.url().endsWith("/api/payments/webhooks/yoco")
                    ? found.url().substring(0, found.url().length() - "/api/payments/webhooks/yoco".length())
                    : found.url();
                tx.execute(() -> {
                    ProviderSettings head = configurations.lock("yoco");
                    access.configure(actor);
                    if (operations.referenced(mode, backend)) {
                        throw PaymentException.conflict(
                            "This callback is referenced by existing payments. Retain it for payment and refund notifications.");
                    }
                    ProviderConfiguration current = configuration(mode);
                    if (head.enabled() && head.mode().equals(mode) && current.backendUrl().equals(backend)) {
                        throw PaymentException
                            .conflict("Disable new checkouts and save before removing the current callback.");
                    }
                    return null;
                });
                writer.remove(key(original), target);
            }
            return tx.execute(() -> {
                ProviderSettings head = configurations.lock("yoco");
                access.configure(actor);
                ProviderConfiguration current = configuration(mode);
                configurations.check(new ConfigurationCheck(UUID.randomUUID(), current.id(), false,
                    "Webhook list changed. Test the saved connection again before activation.", actor,
                    clock.instant()));
                if (head.mode().equals(mode)) {
                    configurations.save(new ProviderSettings("yoco", mode, false, false, true, head.version() + 1));
                }
                operations.finish(request, "APPLIED", target, null);
                configurations.audit("yoco", mode, head.version(), "WEBHOOK_REMOVED", note, "webhook registration",
                    actor, clock.instant());
                return new Result(request, "APPLIED", "Webhook removed. Existing provider payments were not changed.");
            });
        } catch (PaymentException blocked) {
            tx.execute(() -> {
                operations.finish(request, "UNKNOWN", target, null);
                return null;
            });
            if (blocked.kind() == PaymentException.Kind.CONFLICT) {
                tx.execute(() -> {
                    operations.finish(request, "CLOSED", target, null);
                    return null;
                });
                throw blocked;
            }
            return new Result(request, "UNKNOWN",
                "Removal could not be confirmed. Refresh the list and retry the same request.");
        } catch (RuntimeException failure) {
            tx.execute(() -> {
                operations.finish(request, "UNKNOWN", target, null);
                return null;
            });
            return new Result(request, "UNKNOWN",
                "Removal could not be confirmed. Refresh the list and retry the same request.");
        }
    }
}
