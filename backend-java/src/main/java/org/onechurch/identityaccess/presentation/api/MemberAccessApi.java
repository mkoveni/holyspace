package org.onechurch.identityaccess.presentation.api;
import org.onechurch.identityaccess.application.query.listmemberaccess.ListMemberAccessHandler;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PutMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseStatus;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

import java.util.UUID;
@Tag(name = "Member access", description = "Verified general member identity. Requires identity.manage and people.manage; grants no financial, household or guardian authority.")
public interface MemberAccessApi {
    record LinkBody(@NotNull UUID personId, boolean active, @Min(0) long expectedVersion,
        @NotBlank @Size(max = 1000) String evidence) {}
    @GetMapping("/api/member-access")
    @Operation(summary = "List verified member access")
    ResponseEntity<ListMemberAccessHandler.Page> list(@RequestParam(defaultValue = "25") int limit,
        @RequestParam(defaultValue = "0") int offset);
    @PutMapping("/api/member-access/{accountId}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Verify, revoke or restore a member account", description = "Explicit identity evidence and current version are required. A verified link cannot be reassigned to another person. Existing verified financial identity must agree.")
    void verify(@PathVariable UUID accountId, @Valid @RequestBody LinkBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
}
