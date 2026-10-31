package org.onechurch.communications.presentation;
import org.onechurch.communications.application.command.setmynotificationpreference.SetMyNotificationPreferenceCommand;
import org.onechurch.communications.application.command.setmynotificationpreference.SetMyNotificationPreferenceHandler;
import org.onechurch.communications.application.command.setmynotificationread.SetMyNotificationReadCommand;
import org.onechurch.communications.application.command.setmynotificationread.SetMyNotificationReadHandler;
import org.onechurch.communications.application.contract.InboxReader;
import org.onechurch.communications.application.query.getmynotificationpreferences.GetMyNotificationPreferencesHandler;
import org.onechurch.communications.application.query.getmynotificationpreferences.GetMyNotificationPreferencesQuery;
import org.onechurch.communications.application.query.listmynotifications.ListMyNotificationsHandler;
import org.onechurch.communications.application.query.listmynotifications.ListMyNotificationsQuery;
import org.onechurch.communications.presentation.api.MemberInboxApi;
import org.springframework.http.CacheControl;
import org.springframework.http.HttpHeaders;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.web.bind.annotation.ModelAttribute;
import org.springframework.web.bind.annotation.RestController;

import jakarta.servlet.http.HttpServletResponse;

import java.util.List;
import java.util.UUID;
@RestController
@PreAuthorize("isAuthenticated()")
public class MemberInboxController implements MemberInboxApi {
    private final ListMyNotificationsHandler list;
    private final GetMyNotificationPreferencesHandler preferences;
    private final SetMyNotificationReadHandler read;
    private final SetMyNotificationPreferenceHandler preference;
    public MemberInboxController(ListMyNotificationsHandler list, GetMyNotificationPreferencesHandler preferences,
        SetMyNotificationReadHandler read, SetMyNotificationPreferenceHandler preference) {
        this.list = list;
        this.preferences = preferences;
        this.read = read;
        this.preference = preference;
    }
    @ModelAttribute
    void noStore(HttpServletResponse response) {
        response.setHeader(HttpHeaders.CACHE_CONTROL, CacheControl.noStore().getHeaderValue());
    }
    @Override
    public InboxReader.MemberInbox list(boolean unreadOnly, int limit, int offset) {
        return list.handle(new ListMyNotificationsQuery(unreadOnly, limit, offset));
    }
    @Override
    public void read(UUID id, NotificationReadRequest body) {
        read.handle(new SetMyNotificationReadCommand(id, body.read()));
    }
    @Override
    public List<InboxReader.MemberNotificationPreference> preferences() {
        return preferences.handle(new GetMyNotificationPreferencesQuery());
    }
    @Override
    public void preference(String topic, NotificationPreferenceRequest body) {
        preference.handle(new SetMyNotificationPreferenceCommand(topic, body.enabled(), body.expectedVersion()));
    }
}
