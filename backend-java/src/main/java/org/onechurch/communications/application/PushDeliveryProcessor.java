package org.onechurch.communications.application;
import org.onechurch.communications.application.contract.PushSettings;
import org.onechurch.communications.application.contract.PushTokens;
import org.onechurch.communications.application.contract.PushTransport;
import org.onechurch.communications.domain.PushDeliveryPolicy;
import org.onechurch.communications.domain.PushDevice;
import org.onechurch.identityaccess.application.contract.AccountDirectory;
import org.onechurch.identityaccess.application.contract.SessionReader;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.util.Optional;
import java.util.UUID;
/**
 * Trusted bounded outbox adapter entry point; authority is restricted to persisted member/device opt-ins and live
 * sessions.
 */
public final class PushDeliveryProcessor {
    private final PushQueue queue;
    private final PushTransport transport;
    private final PushTokens tokens;
    private final PushSettings settings;
    private final AccountDirectory accounts;
    private final SessionReader sessions;
    private final UnitOfWork tx;
    private final Clock clock;
    public PushDeliveryProcessor(PushQueue queue, PushTransport transport, PushTokens tokens, PushSettings settings,
        AccountDirectory accounts, SessionReader sessions, UnitOfWork tx, Clock clock) {
        this.queue = queue;
        this.transport = transport;
        this.tokens = tokens;
        this.settings = settings;
        this.accounts = accounts;
        this.sessions = sessions;
        this.tx = tx;
        this.clock = clock;
    }
    public void runDue() {
        if (!settings.enabled() || !tokens.available()) {
            return;
        }
        tx.execute(() -> {
            queue.recover(clock.instant());
            return null;
        });
        queue.due(clock.instant()).forEach(this::dispatch);
    }
    public void dispatch(UUID id) {
        if (!settings.enabled() || !tokens.available()) {
            return;
        }
        Optional<PushQueue.Job> claimed = tx.execute(() -> queue.claim(id, clock.instant()));
        if (claimed.isEmpty()) {
            return;
        }
        PushQueue.Job job = claimed.orElseThrow();
        Instant at = clock.instant();
        boolean receipt = job.status().equals("CHECKING");
        Optional<PushDevice> device = queue.eligible(job, at)
            .filter(d -> d.projectId().equals(settings.projectId()) && accounts.active(d.accountId()))
            .filter(d -> sessions.findById(d.sessionId())
                .filter(s -> s.accountId().equals(d.accountId()) && s.expiresAt().isAfter(at))
                .isPresent());
        if (device.isEmpty() || PushDeliveryPolicy.expired(job.createdAt(), at)) {
            finish(job, "CANCELLED", at, job.ticketId(), "ELIGIBILITY_CHANGED");
            return;
        }
        PushTransport.Result result;
        try {
            result = receipt ? transport.receipt(job.ticketId())
                : transport.send(tokens.decrypt(device.orElseThrow().encryptedToken()), job.notificationId());
        } catch (RuntimeException e) {
            result = new PushTransport.Result(receipt ? "PENDING" : "UNKNOWN", job.ticketId(), "TRANSPORT_UNCERTAIN");
        }
        String state = result.state();
        Instant due = at;
        String ticket = result.ticketId() == null ? job.ticketId() : result.ticketId();
        if (state.equals("ACCEPTED")) {
            state = "TICKET";
            due = at.plusSeconds(900);
        } else if (state.equals("PENDING")) {
            state = "TICKET";
            due = at.plusSeconds(900);
        } else if (state.equals("RETRY")) {
            state = receipt ? "TICKET" : PushDeliveryPolicy.retryable(job.attempts()) ? "RETRY" : "FAILED";
            due = PushDeliveryPolicy.retryAt(at, job.attempts());
        }
        finish(job, state, due, ticket, result.errorCode());
    }
    private void finish(PushQueue.Job job, String state, Instant due, String ticket, String error) {
        tx.execute(() -> {
            queue.finish(job, state, due, ticket, error, clock.instant());
            return null;
        });
    }
}
