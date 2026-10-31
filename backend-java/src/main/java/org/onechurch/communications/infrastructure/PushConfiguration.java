package org.onechurch.communications.infrastructure;
import org.onechurch.communications.application.PushDeliveryProcessor;
import org.onechurch.communications.application.PushDeviceActions;
import org.onechurch.communications.application.PushQueue;
import org.onechurch.communications.application.command.registermypushdevice.RegisterMyPushDeviceHandler;
import org.onechurch.communications.application.command.revokemypushdevice.RevokeMyPushDeviceHandler;
import org.onechurch.communications.application.command.setmypushpreference.SetMyPushPreferenceHandler;
import org.onechurch.communications.application.contract.PushReader;
import org.onechurch.communications.application.contract.PushSettings;
import org.onechurch.communications.application.contract.PushTokens;
import org.onechurch.communications.application.contract.PushTransport;
import org.onechurch.communications.application.contract.PushWriter;
import org.onechurch.communications.application.query.getmynotification.GetMyNotificationHandler;
import org.onechurch.communications.application.query.getmypushsettings.GetMyPushSettingsHandler;
import org.onechurch.communications.domain.repository.PushDeviceRepository;
import org.onechurch.identityaccess.application.contract.AccountDirectory;
import org.onechurch.identityaccess.application.contract.AuthenticatedAccount;
import org.onechurch.identityaccess.application.contract.CurrentActor;
import org.onechurch.identityaccess.application.contract.SessionReader;
import org.onechurch.identityaccess.application.contract.TokenCodec;
import org.onechurch.shared.application.UnitOfWork;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import java.time.Clock;

@Configuration
public class PushConfiguration {
    @Bean
    PushDeviceActions pushDeviceActions(AuthenticatedAccount auth, CurrentActor actor, SessionReader sessions,
        PushDeviceRepository devices, TokenCodec hashes, PushTokens tokens, PushSettings settings, UnitOfWork tx,
        Clock clock) {
        return new PushDeviceActions(auth, actor, sessions, devices, hashes, tokens, settings, tx, clock);
    }
    @Bean
    PushDeliveryProcessor pushDeliveryProcessor(PushQueue queue, PushTransport transport, PushTokens tokens,
        PushSettings settings, AccountDirectory accounts, SessionReader sessions, UnitOfWork tx, Clock clock) {
        return new PushDeliveryProcessor(queue, transport, tokens, settings, accounts, sessions, tx, clock);
    }
    @Bean
    RegisterMyPushDeviceHandler registerMyPushDevice(AuthenticatedAccount auth, PushDeviceActions actions) {
        return new RegisterMyPushDeviceHandler(auth, actions);
    }
    @Bean
    RevokeMyPushDeviceHandler revokeMyPushDevice(AuthenticatedAccount auth, PushDeviceActions actions) {
        return new RevokeMyPushDeviceHandler(auth, actions);
    }
    @Bean
    SetMyPushPreferenceHandler setMyPushPreference(AuthenticatedAccount auth, PushWriter writer, UnitOfWork tx) {
        return new SetMyPushPreferenceHandler(auth, writer, tx);
    }
    @Bean
    GetMyPushSettingsHandler getMyPushSettings(AuthenticatedAccount auth, PushReader reader, PushSettings settings,
        CurrentActor actor) {
        return new GetMyPushSettingsHandler(auth, reader, settings, actor);
    }
    @Bean
    GetMyNotificationHandler getMyNotification(AuthenticatedAccount auth, PushReader reader) {
        return new GetMyNotificationHandler(auth, reader);
    }
}
