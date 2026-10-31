package org.onechurch.communities.presentation;
import org.onechurch.communities.domain.CommunityException;
import org.onechurch.events.application.contract.CommunityCalendar;
import org.springframework.dao.DataIntegrityViolationException;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.ExceptionHandler;
import org.springframework.web.bind.annotation.RestControllerAdvice;

import java.util.Map;
@RestControllerAdvice(basePackages = "org.onechurch.communities.presentation")
public class CommunityExceptionHandler {
    @ExceptionHandler(CommunityException.class)
    public ResponseEntity<Map<String, String>> failure(CommunityException e) {
        return ResponseEntity.status(switch (e.kind()) {
            case INVALID -> 400;
            case NOT_FOUND -> 404;
            case CONFLICT -> 409;
            case FORBIDDEN -> 403;
        }).body(Map.of("message", e.getMessage()));
    }
    @ExceptionHandler(CommunityCalendar.Failure.class)
    public ResponseEntity<Map<String, String>> calendar(CommunityCalendar.Failure e) {
        return ResponseEntity.status(e.status()).body(Map.of("message", e.getMessage()));
    }
    @ExceptionHandler(DataIntegrityViolationException.class)
    public ResponseEntity<Map<String, String>> constraint(DataIntegrityViolationException e) {
        return ResponseEntity.status(409)
            .body(Map.of("message",
                "This membership, leadership appointment, meeting or request conflicts with an existing record. Refresh before trying again."));
    }
}
