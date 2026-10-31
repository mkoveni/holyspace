package org.onechurch.giving.presentation;
import org.onechurch.giving.application.command.createfund.CreateFundCommand;
import org.onechurch.giving.application.command.createfund.CreateFundHandler;
import org.onechurch.giving.application.command.recordgift.RecordGiftCommand;
import org.onechurch.giving.application.command.recordgift.RecordGiftHandler;
import org.onechurch.giving.application.command.reversegift.ReverseGiftCommand;
import org.onechurch.giving.application.command.reversegift.ReverseGiftHandler;
import org.onechurch.giving.application.command.updatefund.UpdateFundCommand;
import org.onechurch.giving.application.command.updatefund.UpdateFundHandler;
import org.onechurch.giving.application.contract.GivingReader;
import org.onechurch.giving.application.query.getgift.GetGiftHandler;
import org.onechurch.giving.application.query.getgift.GetGiftQuery;
import org.onechurch.giving.application.query.getgivingtotals.GetGivingTotalsHandler;
import org.onechurch.giving.application.query.getgivingtotals.GetGivingTotalsQuery;
import org.onechurch.giving.application.query.listgifts.ListGiftsHandler;
import org.onechurch.giving.application.query.listgifts.ListGiftsQuery;
import org.onechurch.giving.application.query.listgivingfunds.ListGivingFundsHandler;
import org.onechurch.giving.application.query.listgivingfunds.ListGivingFundsQuery;
import org.onechurch.giving.presentation.api.GivingApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.time.LocalDate;
import java.util.List;
import java.util.UUID;
@RestController
public class GivingController implements GivingApi {
    private final CreateFundHandler createFund;
    private final UpdateFundHandler updateFund;
    private final RecordGiftHandler recordGift;
    private final ReverseGiftHandler reverseGift;
    private final ListGivingFundsHandler listGivingFunds;
    private final ListGiftsHandler listGifts;
    private final GetGiftHandler getGift;
    private final GetGivingTotalsHandler getGivingTotals;
    public GivingController(CreateFundHandler createFund, UpdateFundHandler updateFund, RecordGiftHandler recordGift,
        ReverseGiftHandler reverseGift, ListGivingFundsHandler listGivingFunds, ListGiftsHandler listGifts,
        GetGiftHandler getGift, GetGivingTotalsHandler getGivingTotals) {
        this.createFund = createFund;
        this.updateFund = updateFund;
        this.recordGift = recordGift;
        this.reverseGift = reverseGift;
        this.listGivingFunds = listGivingFunds;
        this.listGifts = listGifts;
        this.getGift = getGift;
        this.getGivingTotals = getGivingTotals;
    }
    @Override
    @PreAuthorize("hasAnyAuthority('giving.read','giving.manage')")
    public GivingReader.FundPage funds(boolean archived, String search, int limit, int offset) {
        return listGivingFunds.handle(new ListGivingFundsQuery(archived, search, limit, offset));
    }
    @Override
    @PreAuthorize("hasAuthority('giving.manage')")
    public Created createFund(CreateFundBody body, OAuth2AuthenticatedPrincipal actor) {
        return new Created(createFund.handle(new CreateFundCommand(UUID.fromString(actor.getName()), body.requestId(),
            body.name(), body.description())));
    }
    @Override
    @PreAuthorize("hasAuthority('giving.manage')")
    public void updateFund(UUID fundId, UpdateFundBody body, OAuth2AuthenticatedPrincipal actor) {
        updateFund.handle(new UpdateFundCommand(UUID.fromString(actor.getName()), fundId, body.expectedVersion(),
            body.name(), body.description(), body.active(), body.reason()));
    }
    @Override
    @PreAuthorize("hasAnyAuthority('giving.read','giving.manage')")
    public GivingReader.GiftPage gifts(LocalDate from, LocalDate to, String currency, UUID fundId, UUID donorId,
        int limit, int offset) {
        return listGifts
            .handle(new ListGiftsQuery(new GivingReader.Filter(from, to, currency, fundId, donorId, limit, offset)));
    }
    @Override
    @PreAuthorize("hasAnyAuthority('giving.read','giving.manage')")
    public GivingReader.Detail gift(UUID giftId) {
        return getGift.handle(new GetGiftQuery(giftId));
    }
    @Override
    @PreAuthorize("hasAuthority('giving.manage')")
    public Created recordGift(RecordGiftBody body, OAuth2AuthenticatedPrincipal actor) {
        return new Created(recordGift.handle(new RecordGiftCommand(UUID.fromString(actor.getName()), body.requestId(),
            body.donorId(), body.amountMinor().longValueExact(), body.currency(), body.receivedOn(), body.method(),
            body.externalReference(), body.allocations().stream().map(AllocationBody::allocation).toList())));
    }
    @Override
    @PreAuthorize("hasAuthority('giving.reverse')")
    public Created reverseGift(UUID giftId, ReverseGiftBody body, OAuth2AuthenticatedPrincipal actor) {
        return new Created(reverseGift.handle(new ReverseGiftCommand(UUID.fromString(actor.getName()), body.requestId(),
            giftId, body.reversedOn(), body.reason())));
    }
    @Override
    @PreAuthorize("hasAnyAuthority('giving.read','giving.manage')")
    public List<GivingReader.Total> totals(LocalDate from, LocalDate to, String currency, UUID fundId, UUID donorId) {
        return getGivingTotals
            .handle(new GetGivingTotalsQuery(new GivingReader.Filter(from, to, currency, fundId, donorId, 25, 0)));
    }
}
