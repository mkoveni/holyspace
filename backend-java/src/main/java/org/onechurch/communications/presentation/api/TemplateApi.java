package org.onechurch.communications.presentation.api;
import org.onechurch.communications.application.contract.TemplateReader;
import org.onechurch.communications.domain.TemplateContent;
import org.springframework.http.HttpStatus;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
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
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

import java.util.List;
import java.util.Map;
import java.util.UUID;
@Tag(name = "Communication templates")
public interface TemplateApi {
    record Created(UUID id) {}
    record Draft(@NotBlank @Size(max = 100) String key, @NotBlank @Size(max = 150) String name,
        @NotBlank @Size(max = 200) String subject, @NotBlank @Size(max = 50000) String html,
        @NotNull @Size(max = 30) List<String> variables) {}
    record Edit(@Min(0) long expectedVersion, @NotBlank @Size(max = 150) String name,
        @NotBlank @Size(max = 200) String subject, @NotBlank @Size(max = 50000) String html,
        @NotNull @Size(max = 30) List<String> variables) {}
    record Change(@Min(0) long expectedVersion) {}
    record Preview(@NotBlank @Size(max = 200) String subject, @NotBlank @Size(max = 50000) String html,
        @NotNull @Size(max = 30) List<String> variables, @NotNull @Size(max = 30) Map<String, String> values) {}
    @GetMapping("/api/communications/templates")
    @Operation(summary = "Browse email templates", description = "Requires communications.templates.read. Bounded metadata and editable content; no recipient data.")
    TemplateReader.Page list(@RequestParam(defaultValue = "") String search,
        @RequestParam(defaultValue = "25") int limit, @RequestParam(defaultValue = "0") int offset);
    @GetMapping("/api/communications/templates/{id}")
    @Operation(summary = "Read a template draft", description = "Requires communications.templates.read. The latest published revision remains active while a draft is edited.")
    TemplateReader.View get(@PathVariable UUID id);
    @PostMapping("/api/communications/templates")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "Create an email template draft", description = "Requires communications.templates.manage. Safe rich text and explicit variables; keys are unique and immutable.")
    Created create(@Valid @RequestBody Draft body, @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @PutMapping("/api/communications/templates/{id}")
    @Operation(summary = "Save a versioned draft", description = "Requires communications.templates.manage. Sanitizes rich text. Variables are text-only placeholders such as {{guardianName}}; scripts, styles, images and variable link addresses are excluded.")
    Created update(@PathVariable UUID id, @Valid @RequestBody Edit body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @PostMapping("/api/communications/templates/{id}/publish")
    @Operation(summary = "Publish a revision", description = "Requires communications.templates.manage. Retains immutable published revision history. Already approved deliveries keep their exact content. Children templates must retain the message variable.")
    Created publish(@PathVariable UUID id, @Valid @RequestBody Change body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @PostMapping("/api/communications/templates/{id}/archive")
    @Operation(summary = "Archive a template", description = "Requires communications.templates.manage. Stops new rendering; existing approved messages remain unchanged.")
    Created archive(@PathVariable UUID id, @Valid @RequestBody Change body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @GetMapping("/api/communications/templates/{id}/revisions")
    @Operation(summary = "Read published revision history", description = "Requires communications.templates.read. Read-only and paginated.")
    List<TemplateReader.Revision> revisions(@PathVariable UUID id, @RequestParam(defaultValue = "25") int limit,
        @RequestParam(defaultValue = "0") int offset);
    @PostMapping("/api/communications/templates/preview")
    @Operation(summary = "Preview unsaved email content", description = "Requires communications.templates.read. Sanitizes content and escapes values; rejects missing/unknown variables. Does not save or send.")
    TemplateContent.Rendered preview(@Valid @RequestBody Preview body);
}
