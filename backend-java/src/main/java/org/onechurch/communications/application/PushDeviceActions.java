package org.onechurch.communications.application;
import org.onechurch.communications.application.contract.PushSettings;
import org.onechurch.communications.application.contract.PushTokens;
import org.onechurch.communications.domain.CommunicationException;
import org.onechurch.communications.domain.PushDevice;
import org.onechurch.communications.domain.repository.PushDeviceRepository;
import org.onechurch.identityaccess.application.contract.AuthenticatedAccount;
import org.onechurch.identityaccess.application.contract.CurrentActor;
import org.onechurch.identityaccess.application.contract.SessionReader;
import org.onechurch.identityaccess.application.contract.TokenCodec;
import org.onechurch.shared.application.UnitOfWork;

import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.time.Clock;
import java.time.Instant;
import java.util.Set;
import java.util.UUID;
public final class PushDeviceActions {
    private final AuthenticatedAccount auth;
    private final CurrentActor actor;
    private final SessionReader sessions;
    private final PushDeviceRepository devices;
    private final TokenCodec hashes;
    private final PushTokens tokens;
    private final PushSettings settings;
    private final UnitOfWork tx;
    private final Clock clock;
    public PushDeviceActions(AuthenticatedAccount auth, CurrentActor actor, SessionReader sessions,
        PushDeviceRepository devices, TokenCodec hashes, PushTokens tokens, PushSettings settings, UnitOfWork tx,
        Clock clock) {
        this.auth = auth;
        this.actor = actor;
        this.sessions = sessions;
        this.devices = devices;
        this.hashes = hashes;
        this.tokens = tokens;
        this.settings = settings;
        this.tx = tx;
        this.clock = clock;
    }
    public void register(UUID id, String secret, String token, String platform, UUID project) {
        UUID account = auth.requireAccount();
        UUID session = actor.current().orElseThrow(CommunicationException::forbidden).sessionId();
        if (id == null || secret == null || !secret.matches("[A-Za-z0-9_-]{43,100}") || token == null
            || !token.matches("(?:Exponent|Expo)PushToken\\[[A-Za-z0-9_-]{10,200}\\]")
            || !Set.of("android", "ios").contains(platform == null ? "" : platform)) {
            throw CommunicationException.invalid("Choose a valid device push registration.");
        }
        if (!settings.enabled() || !tokens.available() || settings.projectId() == null
            || !settings.projectId().equals(project)) {
            throw CommunicationException.conflict("Your church has not completed device push setup.");
        }
        tx.execute(() -> {
            Instant at = clock.instant();
            Instant expiry = sessions.findById(session)
                .filter(s -> s.accountId().equals(account) && s.expiresAt().isAfter(at))
                .map(SessionReader.Session::expiresAt)
                .orElseThrow(CommunicationException::forbidden);
            Instant lease = expiry.isBefore(at.plusSeconds(2592000)) ? expiry : at.plusSeconds(2592000);
            PushDevice draft = new PushDevice(id, account, session, hashes.hash(secret), hashes.hash(token),
                tokens.encrypt(token), project, platform, "ACTIVE", 0, lease, at);
            devices.ensure(draft);
            PushDevice old = devices.lock(id).orElseThrow();
            auth.requireAccount();
            if (!MessageDigest.isEqual(old.secretHash().getBytes(StandardCharsets.UTF_8),
                draft.secretHash().getBytes(StandardCharsets.UTF_8))) {
                throw CommunicationException.forbidden();
            }
            if (!old.accountId().equals(account)
                && sessions.findById(old.sessionId()).filter(s -> s.expiresAt().isAfter(at)).isPresent()) {
                throw CommunicationException
                    .conflict("Sign out of the previous account before enabling push on this device.");
            }
            boolean same = old.accountId().equals(account) && old.sessionId().equals(session)
                && old.tokenHash().equals(draft.tokenHash()) && old.projectId().equals(project)
                && old.platform().equals(platform) && old.status().equals("ACTIVE");
            devices
                .save(new PushDevice(id, account, session, old.secretHash(), draft.tokenHash(), draft.encryptedToken(),
                    project, platform, "ACTIVE", same ? old.revision() : old.revision() + 1, lease, at));
            auth.requireAccount();
            return null;
        });
    }
    public void revoke(UUID id) {
        UUID account = auth.requireAccount();
        tx.execute(() -> {
            PushDevice device = devices.lock(id)
                .filter(d -> d.accountId().equals(account))
                .orElseThrow(() -> CommunicationException.missing("Push device"));
            auth.requireAccount();
            if (!device.status().equals("REVOKED")) {
                devices.save(device.revoke("REVOKED", clock.instant()));
            }
            auth.requireAccount();
            return null;
        });
    }
}
