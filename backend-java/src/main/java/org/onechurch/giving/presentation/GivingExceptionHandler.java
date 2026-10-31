package org.onechurch.giving.presentation;
import org.onechurch.giving.domain.GivingException;
import org.springframework.dao.DataIntegrityViolationException;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.ExceptionHandler;
import org.springframework.web.bind.annotation.RestControllerAdvice;

import java.util.Map;
@RestControllerAdvice(basePackages = {"org.onechurch.giving.presentation", "org.onechurch.payments.presentation"})
public class GivingExceptionHandler {
    @ExceptionHandler(GivingException.class)
    public ResponseEntity<Map<String, String>> failure(GivingException e) {
        return ResponseEntity.status(switch (e.kind()) {
            case INVALID -> 400;
            case FORBIDDEN -> 403;
            case NOT_FOUND -> 404;
            case CONFLICT -> 409;
        }).body(Map.of("message", e.getMessage()));
    }
    @ExceptionHandler(DataIntegrityViolationException.class)
    public ResponseEntity<Map<String, String>> constraint(DataIntegrityViolationException e) {
        return ResponseEntity.status(409)
            .body(Map.of("message",
                "This fund name, gift reference, reversal or request conflicts with existing Giving evidence. Refresh and check the ledger before retrying."));
    }
}
