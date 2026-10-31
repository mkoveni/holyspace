package org.onechurch.identityaccess.infrastructure.security;

import org.springframework.boot.autoconfigure.condition.ConditionalOnWebApplication;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.http.HttpMethod;
import org.springframework.security.config.Customizer;
import org.springframework.security.config.annotation.method.configuration.EnableMethodSecurity;
import org.springframework.security.config.annotation.web.builders.HttpSecurity;
import org.springframework.security.config.http.SessionCreationPolicy;
import org.springframework.security.web.SecurityFilterChain;
import org.springframework.web.cors.UrlBasedCorsConfigurationSource;

import jakarta.servlet.DispatcherType;

@Configuration
@EnableMethodSecurity
@ConditionalOnWebApplication(type = ConditionalOnWebApplication.Type.SERVLET)
public class SecurityConfiguration {

    @Bean
    SecurityFilterChain apiSecurity(HttpSecurity http, UrlBasedCorsConfigurationSource apiCorsConfigurationSource)
        throws Exception {
        return http.cors(cors -> cors.configurationSource(apiCorsConfigurationSource))
            .csrf(csrf -> csrf.disable())
            .sessionManagement(session -> session.sessionCreationPolicy(SessionCreationPolicy.STATELESS))
            .requestCache(cache -> cache.disable())
            .authorizeHttpRequests(auth -> auth.dispatcherTypeMatchers(DispatcherType.ERROR)
                .permitAll()
                .requestMatchers(HttpMethod.GET, "/actuator/health", "/actuator/health/liveness",
                    "/actuator/health/readiness", "/docs", "/docs/**", "/v3/api-docs", "/v3/api-docs/**",
                    "/v3/api-docs.yaml", "/scalar/**", "/api/mobile-checkouts/*")
                .permitAll()
                .requestMatchers(HttpMethod.POST, "/api/login_check", "/api/payments/webhooks/payfast",
                    "/api/payments/webhooks/yoco", "/api/mobile-checkouts/*")
                .permitAll()
                .requestMatchers("/api/**")
                .authenticated()
                .anyRequest()
                .denyAll())
            .oauth2ResourceServer(resource -> resource.opaqueToken(Customizer.withDefaults())
                .authenticationEntryPoint((request, response, error) -> {
                    response.setStatus(401);
                    response.setContentType("application/json");
                    response.setHeader("WWW-Authenticate", "Bearer");
                    response.getWriter().write("{\"message\":\"Authentication required.\"}");
                }))
            .exceptionHandling(errors -> errors.authenticationEntryPoint((request, response, error) -> {
                response.setStatus(401);
                response.setContentType("application/json");
                response.setHeader("WWW-Authenticate", "Bearer");
                response.getWriter().write("{\"message\":\"Authentication required.\"}");
            }).accessDeniedHandler((request, response, error) -> {
                response.setStatus(403);
                response.setContentType("application/json");
                response.getWriter().write("{\"message\":\"Permission denied.\"}");
            }))
            .build();
    }
}
