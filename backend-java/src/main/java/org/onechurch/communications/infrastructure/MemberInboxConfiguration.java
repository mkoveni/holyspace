package org.onechurch.communications.infrastructure;
import org.onechurch.communications.application.command.setmynotificationpreference.SetMyNotificationPreferenceHandler;
import org.onechurch.communications.application.command.setmynotificationread.SetMyNotificationReadHandler;
import org.onechurch.communications.application.contract.InboxReader;
import org.onechurch.communications.application.contract.InboxWriter;
import org.onechurch.communications.application.query.getmynotificationpreferences.GetMyNotificationPreferencesHandler;
import org.onechurch.communications.application.query.listmynotifications.ListMyNotificationsHandler;
import org.onechurch.identityaccess.application.contract.AuthenticatedAccount;
import org.onechurch.shared.application.UnitOfWork;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import java.time.Clock;
@Configuration
public class MemberInboxConfiguration {
    @Bean
    ListMyNotificationsHandler listMyNotifications(AuthenticatedAccount account, InboxReader reader) {
        return new ListMyNotificationsHandler(account, reader);
    }
    @Bean
    GetMyNotificationPreferencesHandler getMyNotificationPreferences(AuthenticatedAccount account, InboxReader reader) {
        return new GetMyNotificationPreferencesHandler(account, reader);
    }
    @Bean
    SetMyNotificationReadHandler setMyNotificationRead(AuthenticatedAccount account, InboxWriter writer, UnitOfWork tx,
        Clock clock) {
        return new SetMyNotificationReadHandler(account, writer, tx, clock);
    }
    @Bean
    SetMyNotificationPreferenceHandler setMyNotificationPreference(AuthenticatedAccount account, InboxWriter writer,
        UnitOfWork tx) {
        return new SetMyNotificationPreferenceHandler(account, writer, tx);
    }
}
