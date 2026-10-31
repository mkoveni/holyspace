package org.onechurch.payments.presentation;
import org.onechurch.payments.domain.PaymentException;
import org.springframework.dao.DataIntegrityViolationException;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.ExceptionHandler;
import org.springframework.web.bind.annotation.RestControllerAdvice;

import java.util.Map;
@RestControllerAdvice(basePackages = "org.onechurch.payments.presentation")
public class PaymentExceptionHandler {
    @ExceptionHandler(PaymentException.class)
    public ResponseEntity<Map<String, String>> failure(PaymentException e) {
        return ResponseEntity.status(switch (e.kind()) {
            case INVALID -> 400;
            case MISSING -> 404;
            case CONFLICT -> 409;
            case UNAVAILABLE -> 503;
        }).body(Map.of("message", e.getMessage()));
    }
    @ExceptionHandler(DataIntegrityViolationException.class)
    public ResponseEntity<Map<String, String>> constraint(DataIntegrityViolationException e) {
        return ResponseEntity.status(409)
            .body(Map.of("message",
                "This payment reference or monthly arrangement conflicts with existing evidence. Refresh before retrying."));
    }
}
