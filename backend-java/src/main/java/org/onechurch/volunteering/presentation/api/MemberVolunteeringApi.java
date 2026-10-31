package org.onechurch.volunteering.presentation.api;
import org.onechurch.volunteering.application.MemberVolunteerViews;
import org.onechurch.volunteering.application.VolunteerProfiles.ExceptionWindow;
import org.onechurch.volunteering.application.VolunteerProfiles.Weekly;
import org.springframework.http.HttpStatus;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.PutMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseStatus;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

import java.util.List;
import java.util.UUID;
@Tag(name = "My volunteering")
public interface MemberVolunteeringApi {
    record Response(@Min(0) long expectedVersion, @NotNull String decision) {}
    record Availability(@NotNull UUID volunteerId, @Min(0) long expectedVersion,
        @NotNull @Size(max = 100) List<@NotNull @Valid Weekly> weekly,
        @NotNull @Size(max = 100) List<@NotNull @Valid ExceptionWindow> exceptions) {}
    @GetMapping("/api/member/volunteering")
    @Operation(summary = "Read my upcoming duties and availability", description = "Explicit volunteer account link and live volunteering.serve access. Upcoming 90 days, including cancelled duties. No other volunteers, credential evidence or staff notes.")
    MemberVolunteerViews.Overview overview(@RequestParam(defaultValue = "25") int limit,
        @RequestParam(defaultValue = "0") int offset);
    @PostMapping("/api/member/volunteering/invitations/{id}/respond")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Accept or decline my invitation", description = "Only live pending invitations. Preserves readiness rules; accepting does not certify readiness.")
    void respond(@PathVariable UUID id, @Valid @RequestBody Response body);
    @PutMapping("/api/member/volunteering/availability")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Replace my availability", description = "Versioned replacement. Weekly end dates are exclusive. Unavailable exceptions override weekly availability. Existing assignments remain unchanged.")
    void availability(@Valid @RequestBody Availability body);
}
