package org.onechurch.events.presentation;
import org.onechurch.events.application.EventViews;
import org.onechurch.events.application.OccurrenceChangeReview;
import org.onechurch.events.application.command.canceleventoccurrences.CancelEventOccurrencesCommand;
import org.onechurch.events.application.command.canceleventoccurrences.CancelEventOccurrencesHandler;
import org.onechurch.events.application.command.rescheduleeventoccurrences.RescheduleEventOccurrencesCommand;
import org.onechurch.events.application.command.rescheduleeventoccurrences.RescheduleEventOccurrencesHandler;
import org.onechurch.events.application.command.restoreeventoccurrence.RestoreEventOccurrenceCommand;
import org.onechurch.events.application.command.restoreeventoccurrence.RestoreEventOccurrenceHandler;
import org.onechurch.events.application.contract.EventJournalReader;
import org.onechurch.events.application.query.geteventoccurrence.GetEventOccurrenceHandler;
import org.onechurch.events.application.query.geteventoccurrence.GetEventOccurrenceQuery;
import org.onechurch.events.application.query.geteventoccurrencehistory.GetEventOccurrenceHistoryHandler;
import org.onechurch.events.application.query.geteventoccurrencehistory.GetEventOccurrenceHistoryQuery;
import org.onechurch.events.application.query.listeventcalendar.ListEventCalendarHandler;
import org.onechurch.events.application.query.listeventcalendar.ListEventCalendarQuery;
import org.onechurch.events.application.query.previeweventoccurrencechange.PreviewEventOccurrenceChangeHandler;
import org.onechurch.events.application.query.previeweventoccurrencechange.PreviewEventOccurrenceChangeQuery;
import org.onechurch.events.presentation.api.EventOccurrencesApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.time.Instant;
import java.util.List;
import java.util.UUID;
@RestController
public class EventOccurrencesController implements EventOccurrencesApi {
    private final RescheduleEventOccurrencesHandler rescheduleEventOccurrences;
    private final CancelEventOccurrencesHandler cancelEventOccurrences;
    private final RestoreEventOccurrenceHandler restoreEventOccurrence;
    private final PreviewEventOccurrenceChangeHandler previewEventOccurrenceChange;
    private final ListEventCalendarHandler listEventCalendar;
    private final GetEventOccurrenceHandler getEventOccurrence;
    private final GetEventOccurrenceHistoryHandler getEventOccurrenceHistory;
    public EventOccurrencesController(RescheduleEventOccurrencesHandler rescheduleEventOccurrences,
        CancelEventOccurrencesHandler cancelEventOccurrences, RestoreEventOccurrenceHandler restoreEventOccurrence,
        PreviewEventOccurrenceChangeHandler previewEventOccurrenceChange, ListEventCalendarHandler listEventCalendar,
        GetEventOccurrenceHandler getEventOccurrence, GetEventOccurrenceHistoryHandler getEventOccurrenceHistory) {
        this.rescheduleEventOccurrences = rescheduleEventOccurrences;
        this.cancelEventOccurrences = cancelEventOccurrences;
        this.restoreEventOccurrence = restoreEventOccurrence;
        this.previewEventOccurrenceChange = previewEventOccurrenceChange;
        this.listEventCalendar = listEventCalendar;
        this.getEventOccurrence = getEventOccurrence;
        this.getEventOccurrenceHistory = getEventOccurrenceHistory;
    }
    @Override
    @PreAuthorize("hasAuthority('events.manage')")
    public void rescheduleEventOccurrences(RescheduleEventOccurrencesRequest b, OAuth2AuthenticatedPrincipal p) {
        rescheduleEventOccurrences.handle(
            new RescheduleEventOccurrencesCommand(UUID.fromString(p.getName()), b.draft(), b.reviewHash(), b.reason()));
    }
    @Override
    @PreAuthorize("hasAuthority('events.manage')")
    public void cancelEventOccurrences(CancelEventOccurrencesRequest b, OAuth2AuthenticatedPrincipal p) {
        cancelEventOccurrences.handle(
            new CancelEventOccurrencesCommand(UUID.fromString(p.getName()), b.draft(), b.reviewHash(), b.reason()));
    }
    @Override
    @PreAuthorize("hasAuthority('events.manage')")
    public void restoreEventOccurrence(RestoreEventOccurrenceRequest b, OAuth2AuthenticatedPrincipal p) {
        restoreEventOccurrence.handle(
            new RestoreEventOccurrenceCommand(UUID.fromString(p.getName()), b.draft(), b.reviewHash(), b.reason()));
    }
    @Override
    @PreAuthorize("hasAuthority('events.read')")
    public OccurrenceChangeReview.Preview previewEventOccurrenceChange(PreviewEventOccurrenceChangeRequest b) {
        return previewEventOccurrenceChange.handle(new PreviewEventOccurrenceChangeQuery(b.draft()));
    }
    @Override
    @PreAuthorize("hasAuthority('events.read')")
    public EventViews.Calendar listEventCalendar(UUID eventId, String search, Instant from, Instant until,
        String status, int limit, int offset) {
        return listEventCalendar
            .handle(new ListEventCalendarQuery(eventId, search, from, until, status, limit, offset));
    }
    @Override
    @PreAuthorize("hasAuthority('events.read')")
    public EventViews.Occurrence getEventOccurrence(UUID id) {
        return getEventOccurrence.handle(new GetEventOccurrenceQuery(id));
    }
    @Override
    @PreAuthorize("hasAuthority('events.read')")
    public List<EventJournalReader.OccurrenceEntry> getEventOccurrenceHistory(UUID id, int limit, int offset) {
        return getEventOccurrenceHistory.handle(new GetEventOccurrenceHistoryQuery(id, limit, offset));
    }
}
